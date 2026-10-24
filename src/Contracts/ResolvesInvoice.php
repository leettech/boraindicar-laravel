<?php

namespace BoraIndicar\Laravel\Contracts;

interface ResolvesInvoice
{
    public function invoiceIdForPaymentIntent(string $paymentIntentId): ?string;
}
