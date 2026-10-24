<?php

use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Exceptions\HubUnavailableException;
use BoraIndicar\Laravel\Jobs\SendEvent;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function pendingPayment(): BoraIndicarEvent
{
    return BoraIndicarEvent::query()->create([
        'type' => EventType::Payment,
        'idempotency_key' => 'payment:in_1',
        'payload' => ['customer_external_id' => '1', 'external_id' => 'in_1', 'amount_cents' => 100, 'currency' => 'BRL', 'paid_at' => '2026-03-01T00:00:00+00:00'],
    ]);
}

function runJob(BoraIndicarEvent $event): void
{
    app()->call([new SendEvent($event->id), 'handle']);
}

it('keeps only array bodies as the response', function (mixed $body) {
    Http::fake(['hub.test/*' => Http::response($body, 200)]);
    $event = pendingPayment();

    runJob($event);

    expect($event->refresh()->status)->toBe(EventStatus::Sent)->and($event->response)->toBeNull();
})->with(['plain text' => 'ok', 'json scalar' => '"ok"', 'empty' => '']);

it('marks the event sent with the response', function () {
    Http::fake(['hub.test/api/v1/payments' => Http::response(['payment' => ['id' => 9]], 201)]);
    $event = pendingPayment();

    runJob($event);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Sent)
        ->and($event->response)->toBe(['payment' => ['id' => 9]])
        ->and($event->sent_at)->not->toBeNull()
        ->and($event->attempts)->toBe(1);
});

it('retries 5xx in-process and then hands it back to the queue', function () {
    Http::fake(['hub.test/*' => Http::response(['message' => 'Server Error'], 500)]);
    $event = pendingPayment();

    expect(fn () => runJob($event))->toThrow(HubUnavailableException::class, 'HTTP 500: Server Error');

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Pending)
        ->and($event->last_error)->toBe('HTTP 500: Server Error')
        ->and($event->attempts)->toBe(1);
    Http::assertSentCount(3);
});

it('hands connection errors back to the queue', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));
    $event = pendingPayment();

    expect(fn () => runJob($event))->toThrow(HubUnavailableException::class);

    expect($event->refresh()->status)->toBe(EventStatus::Pending)
        ->and($event->last_error)->toBe('cURL error 7: Failed to connect');
});

it('fails a 422 right away with the validation errors', function () {
    Http::fake(['hub.test/*' => Http::response(['message' => 'invalid', 'errors' => ['amount_cents' => ['The amount cents field must be at least 1.']]], 422)]);
    $event = pendingPayment();

    runJob($event);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Failed)
        ->and($event->last_error)->toBe('HTTP 422: The amount cents field must be at least 1.')
        ->and($event->response)->toHaveKey('errors');
    Http::assertSentCount(1);
});

it('fails other 4xx without retry', function () {
    Http::fake(['hub.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
    $event = pendingPayment();

    runJob($event);

    expect($event->refresh()->status)->toBe(EventStatus::Failed)
        ->and($event->last_error)->toBe('HTTP 401: Unauthenticated.');
    Http::assertSentCount(1);
});

it('skips events already sent', function () {
    Http::fake();
    $event = pendingPayment();
    $event->markSent(['ok' => true]);

    runJob($event);

    Http::assertNothingSent();
});

it('marks the event failed when the queue gives up', function () {
    $event = pendingPayment();

    (new SendEvent($event->id))->failed(new HubUnavailableException('HTTP 503: down'));

    expect($event->refresh()->status)->toBe(EventStatus::Failed)->and($event->last_error)->toBe('HTTP 503: down');
});

it('backs off exponentially up to an hour, 10 tries, unique per event', function () {
    $job = new SendEvent(42);

    expect($job->tries)->toBe(10)
        ->and($job->backoff())->toHaveCount(9)
        ->and(max($job->backoff()))->toBe(3600)
        ->and($job->backoff())->toBe(collect($job->backoff())->sort()->values()->all())
        ->and($job->uniqueId())->toBe('42');
});

it('holds a unique lock per event for at most an hour', function () {
    expect((new SendEvent(1))->uniqueFor)->toBe(3600);
});

function pendingLead(): BoraIndicarEvent
{
    BoraIndicarReferral::query()->create(['customer_id' => '7', 'code' => 'mar7k2qp']);

    return BoraIndicarEvent::query()->create([
        'type' => EventType::Lead,
        'idempotency_key' => 'lead:7',
        'payload' => ['external_id' => '7', 'code' => 'mar7k2qp', 'signed_up_at' => '2026-03-01T00:00:00+00:00'],
    ]);
}

it('stores the discount the hub promised with the lead', function (array $body, array $expected) {
    Http::fake(['hub.test/api/v1/referrals' => Http::response($body, 201)]);

    runJob(pendingLead());

    expect(BoraIndicarReferral::query()->sole()->only(['attributed', 'hub_referral_id', 'discount_bp', 'discount_months']))->toBe($expected);
})->with([
    'with discount' => [
        ['referral' => ['id' => 42, 'status' => 'lead', 'attributed' => true, 'discount' => ['percent_bp' => 1000, 'months' => 12]]],
        ['attributed' => true, 'hub_referral_id' => '42', 'discount_bp' => 1000, 'discount_months' => 12],
    ],
    'without discount' => [
        ['referral' => ['id' => 42, 'status' => 'lead', 'attributed' => true, 'discount' => null]],
        ['attributed' => true, 'hub_referral_id' => '42', 'discount_bp' => 0, 'discount_months' => null],
    ],
    'not attributed' => [
        ['attributed' => false, 'referral' => null],
        ['attributed' => false, 'hub_referral_id' => null, 'discount_bp' => 0, 'discount_months' => null],
    ],
]);
