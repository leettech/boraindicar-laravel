<?php

use BoraIndicar\Laravel\Contracts\ResolvesInvoice;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Listeners\StripeWebhookListener;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\Fakes\FakeInvoiceResolver;

beforeEach(function () {
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);
    $this->app->instance(ResolvesInvoice::class, new FakeInvoiceResolver(['pi_1QBoraIndicarTest' => 'in_1QAbcInvoice']));
    customer(['stripe_id' => 'cus_BoraIndicarTest']);
});

it('reports invoice.paid as a payment on the base amount without tax', function () {
    event(new WebhookReceived(stripeFixture('invoice.paid')));

    $event = BoraIndicarEvent::query()->sole();
    expect($event->type)->toBe(EventType::Payment)
        ->and($event->payload)->toBe([
            'customer_external_id' => '1',
            'external_id' => 'in_1QAbcInvoice',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'paid_at' => '2026-01-01T01:00:00+00:00',
        ]);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/payments');
});

it('falls back to amount_paid minus taxes without total_excluding_tax', function () {
    $payload = stripeFixture('invoice.paid');
    unset($payload['data']['object']['total_excluding_tax']);

    event(new WebhookReceived($payload));

    expect(BoraIndicarEvent::query()->sole()->payload['amount_cents'])->toBe(10000);
});

it('ignores invoices of unknown customers and zero invoices', function () {
    $unknown = stripeFixture('invoice.paid');
    $unknown['data']['object']['customer'] = 'cus_Nobody';
    $zero = stripeFixture('invoice.paid');
    $zero['data']['object']['id'] = 'in_zero';
    $zero['data']['object']['total_excluding_tax'] = 0;

    event(new WebhookReceived($unknown));
    event(new WebhookReceived($zero));

    expect(BoraIndicarEvent::query()->count())->toBe(0);
});

it('reports a full refund resolving the invoice by payment intent (basil)', function () {
    event(new WebhookReceived(stripeFixture('charge.refunded')));

    $event = BoraIndicarEvent::query()->sole();
    expect($event->type)->toBe(EventType::Refund)
        ->and($event->payload)->toBe(['payment_external_id' => 'in_1QAbcInvoice']);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/refunds');
});

it('reports a partial refund with the cumulative amount from charge.invoice', function () {
    $this->app->instance(ResolvesInvoice::class, new FakeInvoiceResolver);

    event(new WebhookReceived(stripeFixture('charge.refunded.partial')));

    expect(BoraIndicarEvent::query()->sole()->payload)->toBe(['payment_external_id' => 'in_1QAbcInvoice', 'amount_cents' => 4000]);
});

it('ignores a refund whose invoice cannot be found', function () {
    $this->app->instance(ResolvesInvoice::class, new FakeInvoiceResolver);

    event(new WebhookReceived(stripeFixture('charge.refunded')));

    expect(BoraIndicarEvent::query()->count())->toBe(0);
});

it('reports a deleted subscription as a cancel keyed by the subscription', function () {
    event(new WebhookReceived(stripeFixture('customer.subscription.deleted')));

    $event = BoraIndicarEvent::query()->sole();
    expect($event->type)->toBe(EventType::Cancel)
        ->and($event->idempotency_key)->toBe('cancel:1:sub_1QBoraIndicarTest');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/referrals/1/cancel');
});

it('does not queue unrelated webhooks', function () {
    $listener = app(StripeWebhookListener::class);

    expect($listener->shouldQueue(new WebhookReceived(['type' => 'customer.updated'])))->toBeFalse()
        ->and($listener->shouldQueue(new WebhookReceived(['type' => 'invoice.paid'])))->toBeTrue();
});

it('is not registered when auto.cashier is off', function () {
    expect(Event::getRawListeners()[WebhookReceived::class] ?? [])->toContain(StripeWebhookListener::class);

    $this->rebootWith(['boraindicar.auto.cashier' => false]);

    expect(Event::getRawListeners()[WebhookReceived::class] ?? [])->not->toContain(StripeWebhookListener::class);
});

it('ignores an invoice paid entirely with credit balance', function () {
    $payload = stripeFixture('invoice.paid');
    $payload['data']['object']['amount_paid'] = 0;

    event(new WebhookReceived($payload));

    expect(BoraIndicarEvent::query()->count())->toBe(0);
});

it('caps the base at what was charged when credit paid part of the invoice', function () {
    $payload = stripeFixture('invoice.paid');
    $payload['data']['object']['amount_paid'] = 6000;

    event(new WebhookReceived($payload));

    expect(BoraIndicarEvent::query()->sole()->payload['amount_cents'])->toBe(4000);
});
