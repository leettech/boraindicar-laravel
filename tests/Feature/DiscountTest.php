<?php

use BoraIndicar\Laravel\Discount;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Exceptions\HubUnavailableException;
use BoraIndicar\Laravel\Facades\BoraIndicar;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/** The lead of customer 1 still waiting in the queue (its referral row has no discount yet). */
function queuedLead(): BoraIndicarEvent
{
    referralRow(['hub_referral_id' => null, 'discount_bp' => null, 'discount_months' => null]);

    return BoraIndicarEvent::query()->create([
        'type' => EventType::Lead,
        'idempotency_key' => 'lead:1',
        'payload' => ['external_id' => '1', 'email' => 'ana@cliente.com.br', 'code' => 'mar7k2qp'],
    ]);
}

function referralRow(array $attributes = []): BoraIndicarReferral
{
    return BoraIndicarReferral::query()->create([
        'customer_id' => '1',
        'code' => 'mar7k2qp',
        'attributed' => true,
        'hub_referral_id' => '42',
        'discount_bp' => 1000,
        'discount_months' => 12,
        ...$attributes,
    ]);
}

it('reads the discount stored with the lead', function () {
    referralRow();

    expect(BoraIndicar::discount(customer()))->toEqual(new Discount(1000, 12))
        ->and(BoraIndicar::discount('1'))->toEqual(new Discount(1000, 12));
    Http::assertNothingSent();
});

it('has no discount without a referral, without attribution or with zero', function (array $row) {
    if ($row !== []) {
        referralRow($row);
    }

    expect(BoraIndicar::discount('1'))->toBeNull();
    Http::assertNothingSent();
})->with([
    'no referral' => [[]],
    'not attributed' => [['attributed' => false]],
    'zero' => [['discount_bp' => 0, 'discount_months' => null]],
]);

it('asks the hub once for a lead sent before the upgrade', function () {
    Http::fake(['hub.test/api/v1/referrals/*' => Http::response(['referral' => ['id' => 42, 'status' => 'lead', 'attributed' => true, 'discount' => ['percent_bp' => 1250, 'months' => 3]]])]);
    $referral = referralRow(['customer_id' => 'cus/1 a', 'discount_bp' => null, 'discount_months' => null]);

    expect(BoraIndicar::discount('cus/1 a'))->toEqual(new Discount(1250, 3))
        ->and(BoraIndicar::discount('cus/1 a'))->toEqual(new Discount(1250, 3))
        ->and($referral->refresh()->only(['discount_bp', 'discount_months']))->toBe(['discount_bp' => 1250, 'discount_months' => 3]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://hub.test/api/v1/referrals/cus%2F1%20a'
        && $request->hasHeader('Authorization', 'Bearer sk_test'));
});

it('stores no discount when the hub does not know the customer', function () {
    Http::fake(['hub.test/api/v1/referrals/*' => Http::response(['message' => 'Cliente não indicado.'], 404)]);
    $referral = referralRow(['discount_bp' => null, 'discount_months' => null]);

    expect(BoraIndicar::discount('1'))->toBeNull()
        ->and($referral->refresh()->discount_bp)->toBe(0);
});

it('never breaks the checkout when the hub is down', function () {
    Exceptions::fake();
    Http::fake(['hub.test/api/v1/referrals/*' => Http::response(['message' => 'Server Error'], 500)]);
    $referral = referralRow(['discount_bp' => null, 'discount_months' => null]);

    expect(BoraIndicar::discount('1'))->toBeNull()
        ->and($referral->refresh()->discount_bp)->toBeNull();
    Exceptions::assertReported(RuntimeException::class);
    Http::assertSentCount(1);
});

it('delivers a lead still in the queue and takes the discount from its answer', function () {
    Http::fake(['hub.test/api/v1/referrals' => Http::response(['referral' => ['id' => 42, 'status' => 'lead', 'attributed' => true, 'discount' => ['percent_bp' => 1250, 'months' => 3]]], 201)]);
    $lead = queuedLead();

    expect(BoraIndicar::discount('1'))->toEqual(new Discount(1250, 3))
        ->and($lead->refresh()->status)->toBe(EventStatus::Sent);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['external_id'] === '1');
});

it('stores nothing while the queued lead cannot be delivered, so the next call tries again', function () {
    Exceptions::fake();
    Http::fake(['hub.test/api/v1/referrals' => Http::response(['message' => 'Service Unavailable'], 503)]);
    $lead = queuedLead();

    expect(BoraIndicar::discount('1'))->toBeNull()
        ->and(BoraIndicarReferral::query()->sole()->discount_bp)->toBeNull()
        ->and($lead->refresh()->status)->toBe(EventStatus::Pending);

    Exceptions::assertReported(HubUnavailableException::class);
    Http::assertSentCount(1);
});

it('stores no discount when the hub refuses the queued lead', function () {
    Http::fake([
        'hub.test/api/v1/referrals' => Http::response(['message' => 'Código inválido.', 'errors' => ['code' => ['Código inválido.']]], 422),
        'hub.test/api/v1/referrals/*' => Http::response(['message' => 'Cliente não indicado.'], 404),
    ]);
    $lead = queuedLead();

    expect(BoraIndicar::discount('1'))->toBeNull()
        ->and($lead->refresh()->status)->toBe(EventStatus::Failed)
        ->and(BoraIndicarReferral::query()->sole()->discount_bp)->toBe(0);
});

it('does not ask the hub while the integration is disabled', function () {
    config(['boraindicar.enabled' => false]);
    referralRow(['discount_bp' => null, 'discount_months' => null]);

    expect(BoraIndicar::discount('1'))->toBeNull();
    Http::assertNothingSent();
});
