<?php

namespace BoraIndicar\Laravel\Listeners;

use BoraIndicar\Laravel\BoraIndicarManager;
use BoraIndicar\Laravel\Contracts\ResolvesInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Cashier has no handler for invoice.paid nor charge.refunded, so this reads
 * WebhookReceived. Queued: resolving a refund's invoice may call Stripe.
 */
final class StripeWebhookListener implements ShouldQueue
{
    private const TYPES = ['invoice.paid', 'charge.refunded', 'customer.subscription.deleted'];

    public function __construct(
        private readonly BoraIndicarManager $boraindicar,
        private readonly ResolvesInvoice $invoices,
    ) {}

    public function shouldQueue(WebhookReceived $event): bool
    {
        return in_array($event->payload['type'] ?? null, self::TYPES, true);
    }

    /** Called on an instance built without the constructor, hence config(). */
    public function viaConnection(): ?string
    {
        return config('boraindicar.queue.connection');
    }

    public function viaQueue(): ?string
    {
        return config('boraindicar.queue.queue');
    }

    public function handle(WebhookReceived $event): void
    {
        $object = data_get($event->payload, 'data.object', []);

        match ($event->payload['type'] ?? null) {
            'invoice.paid' => $this->invoicePaid($object),
            'charge.refunded' => $this->chargeRefunded($object),
            'customer.subscription.deleted' => $this->subscriptionDeleted($object),
            default => null,
        };
    }

    /** @param array<string, mixed> $invoice */
    private function invoicePaid(array $invoice): void
    {
        $billable = Cashier::findBillable(self::id($invoice['customer'] ?? null));
        $invoiceId = self::id($invoice['id'] ?? null);

        if ($billable === null || $invoiceId === null) {
            return;
        }

        // Never more than what was actually charged: credit balance can pay all
        // or part of an invoice, and that part earns no commission.
        $taxes = (int) ($invoice['tax'] ?? 0) + (int) collect($invoice['total_taxes'] ?? [])->sum('amount');
        $paidWithoutTax = (int) ($invoice['amount_paid'] ?? 0) - $taxes;
        $base = isset($invoice['total_excluding_tax'])
            ? min((int) $invoice['total_excluding_tax'], $paidWithoutTax)
            : $paidWithoutTax;

        if ($base <= 0) {
            return;
        }

        $paidAt = data_get($invoice, 'status_transitions.paid_at') ?? $invoice['created'] ?? null;

        $this->boraindicar->payment(
            $this->boraindicar->customerId($billable),
            $invoiceId,
            $base,
            strtoupper((string) ($invoice['currency'] ?? 'brl')),
            is_int($paidAt) ? CarbonImmutable::createFromTimestampUTC($paidAt) : null,
        );
    }

    /** @param array<string, mixed> $charge */
    private function chargeRefunded(array $charge): void
    {
        $invoiceId = self::id($charge['invoice'] ?? null);
        $paymentIntent = self::id($charge['payment_intent'] ?? null);

        if ($invoiceId === null && $paymentIntent !== null) {
            $invoiceId = $this->invoices->invoiceIdForPaymentIntent($paymentIntent);
        }

        if ($invoiceId === null) {
            return;
        }

        $this->boraindicar->refund(
            $invoiceId,
            ($charge['refunded'] ?? false) ? null : (int) ($charge['amount_refunded'] ?? 0),
        );
    }

    /** @param array<string, mixed> $subscription */
    private function subscriptionDeleted(array $subscription): void
    {
        $billable = Cashier::findBillable(self::id($subscription['customer'] ?? null));

        if ($billable === null) {
            return;
        }

        $this->boraindicar->cancel($billable, self::id($subscription['id'] ?? null));
    }

    private static function id(mixed $reference): ?string
    {
        $id = is_array($reference) ? ($reference['id'] ?? null) : $reference;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
