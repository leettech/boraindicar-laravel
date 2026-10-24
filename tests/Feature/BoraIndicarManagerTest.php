<?php

use BoraIndicar\Laravel\Actions\RetryEvent;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Facades\BoraIndicar;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function hubAccepts(): void
{
    Http::fake([
        'hub.test/api/v1/payments' => Http::response(['payment' => ['id' => 7], 'commission' => ['amount_cents' => 3000, 'status' => 'pending'], 'attributed' => true], 201),
        'hub.test/api/v1/refunds' => Http::response(['payment' => ['id' => 7, 'refunded_cents' => 10000]]),
        'hub.test/api/v1/referrals/*' => Http::response(['status' => 'canceled']),
    ]);
}

it('records and sends a payment', function () {
    hubAccepts();
    $event = BoraIndicar::payment(15, 'in_123', 10000, 'brl', CarbonImmutable::parse('2026-03-01 10:00:00', 'America/Sao_Paulo'));

    expect($event->idempotency_key)->toBe('payment:in_123')
        ->and($event->payload)->toBe([
            'customer_external_id' => '15',
            'external_id' => 'in_123',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'paid_at' => '2026-03-01T13:00:00+00:00',
        ])
        ->and($event->fresh()->status)->toBe(EventStatus::Sent)
        ->and($event->fresh()->response)->toMatchArray(['attributed' => true]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/payments'
        && $request->hasHeader('Authorization', 'Bearer sk_test')
        && $request->data() === $event->payload);
});

it('is idempotent: the same payment twice is one event and one request', function () {
    hubAccepts();
    $first = BoraIndicar::payment(15, 'in_123', 10000);
    $second = BoraIndicar::payment(15, 'in_123', 10000);

    expect($second->is($first))->toBeTrue()
        ->and(BoraIndicarEvent::query()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('omits amount_cents on a full refund and keys partial refunds by the cumulative amount', function () {
    hubAccepts();
    $full = BoraIndicar::refund('in_123');
    $partial = BoraIndicar::refund('in_456', 4000);
    $again = BoraIndicar::refund('in_456', 4000);
    $more = BoraIndicar::refund('in_456', 6000);

    expect($full->payload)->toBe(['payment_external_id' => 'in_123'])
        ->and($full->idempotency_key)->toBe('refund:in_123:total')
        ->and($partial->payload)->toBe(['payment_external_id' => 'in_456', 'amount_cents' => 4000])
        ->and($again->is($partial))->toBeTrue()
        ->and($more->idempotency_key)->toBe('refund:in_456:6000');
    Http::assertSentCount(3);
});

it('cancels through the referral route', function () {
    hubAccepts();
    $event = BoraIndicar::cancel(customer(), 'sub_1');

    expect($event->idempotency_key)->toBe('cancel:1:sub_1');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/referrals/1/cancel' && $request->method() === 'POST');
});

it('resolves the customer id from the configured attribute or a callback', function () {
    $user = customer();

    config(['boraindicar.customer_id' => 'email']);
    expect(BoraIndicar::customerId($user))->toBe('ana@cliente.com.br')
        ->and(BoraIndicar::customerId(['email' => 'b@x.com']))->toBe('b@x.com')
        ->and(BoraIndicar::customerId(99))->toBe('99');

    BoraIndicar::resolveCustomerIdUsing(fn ($customer) => 'user-'.data_get($customer, 'id'));
    expect(BoraIndicar::customerId($user))->toBe('user-1');
});

it('records nothing when disabled', function () {
    config(['boraindicar.enabled' => false]);

    expect(BoraIndicar::payment(1, 'in_1', 100))->toBeNull()
        ->and(BoraIndicar::refund('in_1'))->toBeNull()
        ->and(BoraIndicarEvent::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does not break the caller when the hub is down on the sync queue', function () {
    Http::fakeSequence('hub.test/*')
        ->push(['message' => 'down'], 503)
        ->push(['message' => 'down'], 503)
        ->push(['message' => 'down'], 503)
        ->push(['ok' => true]);

    $event = BoraIndicar::payment(1, 'in_down', 100);

    // No retry on the sync driver: the row is left failed, ready for "Reenviar".
    expect($event->fresh()->status)->toBe(EventStatus::Failed)
        ->and($event->fresh()->last_error)->toBe('HTTP 503: down')
        ->and(app(RetryEvent::class)->handle($event->fresh()))->toBeTrue('the failed sync job must release its unique lock');
});

it('releases the unique lock when the queue push itself fails', function () {
    config(['boraindicar.queue.connection' => 'missing']);

    $event = BoraIndicar::payment(1, 'in_queue_down', 100);

    expect($event->fresh()->status)->toBe(EventStatus::Pending);

    config(['boraindicar.queue.connection' => null]);
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);

    expect(app(RetryEvent::class)->handle($event))->toBeTrue()
        ->and($event->fresh()->status)->toBe(EventStatus::Sent);
});

it('keeps one row per key inside the caller\'s open transaction', function () {
    hubAccepts();

    DB::transaction(function () {
        BoraIndicar::payment(1, 'in_tx', 100);
        BoraIndicar::payment(1, 'in_tx', 100);

        expect(BoraIndicarEvent::query()->where('idempotency_key', 'payment:in_tx')->count())->toBe(1);
    });

    Http::assertSentCount(1);
});
