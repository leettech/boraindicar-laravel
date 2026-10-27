<?php

namespace BoraIndicar\Laravel\Stripe;

use BoraIndicar\Laravel\Contracts\ResolvesInvoice;
use Laravel\Cashier\Cashier;
use Stripe\Service\InvoicePaymentService;

/**
 * Since the Stripe API "basil" a charge no longer points to its invoice; the
 * link lives in the invoice payment of the charge's payment intent. Older
 * stripe-php (Cashier 15) has no invoice payments service, but there the
 * charge still carries `invoice`, so a charge without one is a one-off charge.
 */
final class StripeInvoiceResolver implements ResolvesInvoice
{
    private readonly bool $invoicePayments;

    public function __construct(?bool $invoicePayments = null)
    {
        $this->invoicePayments = $invoicePayments ?? class_exists(InvoicePaymentService::class);
    }

    public function invoiceIdForPaymentIntent(string $paymentIntentId): ?string
    {
        if (! $this->invoicePayments) {
            return null;
        }

        $invoice = data_get(
            Cashier::stripe()->invoicePayments->all([
                'payment' => ['type' => 'payment_intent', 'payment_intent' => $paymentIntentId],
                'limit' => 1,
            ])->toArray(),
            'data.0.invoice',
        );

        $id = is_array($invoice) ? ($invoice['id'] ?? null) : $invoice;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
