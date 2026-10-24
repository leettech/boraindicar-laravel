<?php

use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function failedEvent(string $key, EventStatus $status = EventStatus::Failed): BoraIndicarEvent
{
    return BoraIndicarEvent::query()->forceCreate([
        'type' => EventType::Refund,
        'idempotency_key' => $key,
        'payload' => ['payment_external_id' => $key],
        'status' => $status,
        'attempts' => 3,
        'last_error' => 'HTTP 500',
    ]);
}

it('installs: publishes config and migrations and prints the env', function () {
    $published = fn () => array_merge(
        File::glob(database_path('migrations/*_boraindicar_*_table.php')),
        File::exists(config_path('boraindicar.php')) ? [config_path('boraindicar.php')] : [],
    );

    try {
        $this->artisan('boraindicar:install')
            ->expectsOutputToContain('BORAINDICAR_SECRET=sk_...')
            ->expectsOutputToContain('@boraindicarScript')
            ->expectsOutputToContain('<script src="https://hub.test/js/boraindicar.js" data-key="pk_..." data-domain=".seudominio.com.br" defer></script>')
            ->assertSuccessful();

        expect(File::exists(config_path('boraindicar.php')))->toBeTrue()
            ->and(File::glob(database_path('migrations/*_create_boraindicar_events_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_create_boraindicar_referrals_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_add_discount_to_boraindicar_referrals_table.php')))->toHaveCount(1);
    } finally {
        File::delete($published());
    }
});

it('pings the hub and shows the product', function () {
    Http::fake(['hub.test/api/v1/ping' => Http::response(['product' => ['slug' => 'exemplo', 'name' => 'Exemplo SaaS']])]);

    $this->artisan('boraindicar:ping')->expectsOutputToContain('Conectado ao produto Exemplo SaaS (exemplo)')->assertSuccessful();
});

it('fails the ping on a wrong key', function () {
    Http::fake(['hub.test/api/v1/ping' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $this->artisan('boraindicar:ping')->expectsOutputToContain('HTTP 401: Unauthenticated.')->assertFailed();
});

it('retries one event by id', function () {
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);
    $event = failedEvent('in_1');

    $this->artisan('boraindicar:retry', ['id' => $event->id])->assertSuccessful();

    expect($event->refresh()->status)->toBe(EventStatus::Sent)->and($event->attempts)->toBe(1);
});

it('retries only failed events with --all', function () {
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);
    $failed = [failedEvent('in_1'), failedEvent('in_2')];
    $pending = failedEvent('in_3', EventStatus::Pending);

    $this->artisan('boraindicar:retry', ['--all' => true])->expectsOutputToContain('2 evento(s) reenviado(s).')->assertSuccessful();

    expect(collect($failed)->map(fn ($event) => $event->refresh()->status)->unique()->all())->toBe([EventStatus::Sent])
        ->and($pending->refresh()->status)->toBe(EventStatus::Pending);
    Http::assertSentCount(2);
});

it('asks for an id or --all', function () {
    $this->artisan('boraindicar:retry')->assertFailed();
    $this->artisan('boraindicar:retry', ['id' => 999])->assertFailed();
});
