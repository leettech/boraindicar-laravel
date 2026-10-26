<?php

use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Listeners\RecordLeadOnRegistered;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-03-01 12:00:00');
    Http::fake(['hub.test/api/v1/referrals' => Http::response(['referral' => ['id' => 42, 'status' => 'lead', 'attributed' => true, 'discount' => ['percent_bp' => 1000, 'months' => 12]]], 201)]);

    Route::middleware('web')->post('/register', function () {
        event(new Registered(customer(['created_at' => '2026-03-01 11:59:00'])));

        return 'ok';
    });
});

$cookie = '{"c":"mar7k2qp","k":"01JBX7Z8Q9R0S1T2V3W4X5Y6Z7","t":1772300000,"u":{"utm_source":"instagram"}}';

it('reports the lead with the exact payload and remembers the referral', function () use ($cookie) {
    $response = $this->withUnencryptedCookie('boraindicar', $cookie)->post('/register')->assertOk();

    $event = BoraIndicarEvent::query()->sole();
    expect($event->type)->toBe(EventType::Lead)
        ->and($event->idempotency_key)->toBe('lead:1')
        ->and($event->status)->toBe(EventStatus::Sent)
        ->and($event->payload)->toBe([
            'external_id' => '1',
            'email' => 'ana@cliente.com.br',
            'name' => 'Ana Souza',
            'code' => 'mar7k2qp',
            'click_id' => '01JBX7Z8Q9R0S1T2V3W4X5Y6Z7',
            'utm' => ['utm_source' => 'instagram'],
            'signed_up_at' => '2026-03-01T11:59:00+00:00',
            'clicked_at' => '2026-02-28T17:33:20+00:00',
        ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/referrals'
        && $request->hasHeader('Authorization', 'Bearer sk_test')
        && $request['external_id'] === '1');

    expect(BoraIndicarReferral::query()->sole()->only(['customer_id', 'code', 'click_id', 'utm', 'attributed', 'hub_referral_id', 'discount_bp', 'discount_months']))->toBe([
        'customer_id' => '1',
        'code' => 'mar7k2qp',
        'click_id' => '01JBX7Z8Q9R0S1T2V3W4X5Y6Z7',
        'utm' => ['utm_source' => 'instagram'],
        'attributed' => true,
        'hub_referral_id' => '42',
        'discount_bp' => 1000,
        'discount_months' => 12,
    ]);

    $forgotten = collect($response->headers->all('set-cookie'))->first(fn (string $header) => str_starts_with($header, 'boraindicar=deleted'));
    expect($forgotten)->not->toBeNull();
});

it('uses the session when the cookie is gone (OAuth round trip)', function () {
    $this->withSession(['boraindicar.attribution' => ['c' => 'ze4h9wnc', 'k' => null, 't' => 1772300000, 'u' => []]])
        ->post('/register')
        ->assertOk()
        ->assertSessionMissing('boraindicar.attribution');

    expect(BoraIndicarEvent::query()->sole()->payload['code'])->toBe('ze4h9wnc');
});

it('does nothing without attribution', function () {
    $this->post('/register')->assertOk();

    expect(BoraIndicarEvent::query()->count())->toBe(0)->and(BoraIndicarReferral::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('is not registered when auto.registered is off', function () {
    expect(Event::getRawListeners()[Registered::class] ?? [])->toContain(RecordLeadOnRegistered::class);

    $this->rebootWith(['boraindicar.auto.registered' => false]);

    expect(Event::getRawListeners()[Registered::class] ?? [])->not->toContain(RecordLeadOnRegistered::class);
});
