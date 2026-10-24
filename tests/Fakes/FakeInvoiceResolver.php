<?php

namespace Tests\Fakes;

use BoraIndicar\Laravel\Contracts\ResolvesInvoice;

final class FakeInvoiceResolver implements ResolvesInvoice
{
    /** @param array<string, string> $invoices payment intent id => invoice id */
    public function __construct(private array $invoices = []) {}

    public function invoiceIdForPaymentIntent(string $paymentIntentId): ?string
    {
        return $this->invoices[$paymentIntentId] ?? null;
    }
}
