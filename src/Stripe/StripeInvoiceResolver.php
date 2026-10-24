<?php

namespace BoraIndicar\Laravel\Stripe;

use BoraIndicar\Laravel\Contracts\ResolvesInvoice;
use Laravel\Cashier\Cashier;

/**
 * Since the Stripe API "basil" a charge no longer points to its invoice; the
 * link lives in the invoice payment of the charge's payment intent.
 */
final class StripeInvoiceResolver implements ResolvesInvoice
{
    public function invoiceIdForPaymentIntent(string $paymentIntentId): ?string
    {
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
