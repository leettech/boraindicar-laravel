<?php

use BoraIndicar\Laravel\Actions\RetryEvent;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Jobs\SendEvent;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);

    $this->event = BoraIndicarEvent::query()->forceCreate([
        'type' => EventType::Refund,
        'idempotency_key' => 'refund:in_1:total',
        'payload' => ['payment_external_id' => 'in_1'],
        'status' => EventStatus::Failed,
        'attempts' => 10,
        'last_error' => 'HTTP 500',
    ]);
});

/** Simulates a delivery of the event already waiting in the queue. */
function holdQueueLock(BoraIndicarEvent $event): void
{
    expect((new UniqueLock(app(Repository::class)))->acquire(new SendEvent($event->id)))->toBeTrue();
}

it('queues the delivery and resets the attempt budget', function () {
    expect(app(RetryEvent::class)->handle($this->event))->toBeTrue()
        ->and($this->event->refresh()->status)->toBe(EventStatus::Sent)
        ->and($this->event->attempts)->toBe(1);
});

it('does nothing while a delivery of the event is already queued', function () {
    holdQueueLock($this->event);

    expect(app(RetryEvent::class)->handle($this->event))->toBeFalse()
        ->and(app(RetryEvent::class)->handleFailed())->toBe(0)
        ->and($this->event->refresh()->status)->toBe(EventStatus::Failed)
        ->and($this->event->attempts)->toBe(10);
    Http::assertNothingSent();
});

it('tells the dashboard user when the event was not queued again', function () {
    Gate::define('viewBoraIndicar', fn () => true);
    holdQueueLock($this->event);

    $this->actingAs(customer())
        ->post("/boraindicar/events/{$this->event->id}/retry")
        ->assertSessionHas('boraindicar.flash', "Evento #{$this->event->id} já está na fila de envio.");

    $this->post('/boraindicar/events/retry-failed')->assertSessionHas('boraindicar.flash', '0 evento(s) reenviado(s).');
});

it('tells the console user when the event was not queued again', function () {
    holdQueueLock($this->event);

    $this->artisan('boraindicar:retry', ['id' => $this->event->id])
        ->expectsOutputToContain('já está na fila de envio')
        ->assertSuccessful();
});
