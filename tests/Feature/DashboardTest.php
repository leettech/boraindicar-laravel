<?php

use BoraIndicar\Laravel\Actions\PingHub;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

function outboxRow(EventType $type, string $key, EventStatus $status): BoraIndicarEvent
{
    return BoraIndicarEvent::query()->forceCreate([
        'type' => $type,
        'idempotency_key' => $key,
        'payload' => ['external_id' => '1', 'payment_external_id' => 'in_1'],
        'status' => $status,
        'last_error' => $status === EventStatus::Failed ? 'HTTP 422: inválido' : null,
    ]);
}

it('requires authentication', function () {
    $this->getJson('/boraindicar')->assertUnauthorized();
});

it('is forbidden outside local by default', function () {
    $this->actingAs(customer())->get('/boraindicar')->assertForbidden();
});

it('lists events, counters and referrals for allowed users', function () {
    Gate::define('viewBoraIndicar', fn () => true);
    $user = customer();
    outboxRow(EventType::Payment, 'payment:in_ok', EventStatus::Sent);
    outboxRow(EventType::Refund, 'refund:in_bad:total', EventStatus::Failed);
    BoraIndicarReferral::query()->create(['customer_id' => (string) $user->id, 'code' => 'mar7k2qp', 'attributed' => true]);

    $this->actingAs($user)->get('/boraindicar')
        ->assertOk()
        ->assertSee('Eventos enviados ao hub')
        ->assertSee('payment:in_ok')
        ->assertSee('refund:in_bad:total')
        ->assertSee('HTTP 422: inválido')
        ->assertSee('Reenviar todos com falha')
        ->assertSee('Indicações recentes')
        ->assertSee('ana@cliente.com.br')
        ->assertSee('Atribuída');
});

it('filters by status and type', function () {
    Gate::define('viewBoraIndicar', fn () => true);
    outboxRow(EventType::Payment, 'payment:in_ok', EventStatus::Sent);
    outboxRow(EventType::Payment, 'payment:in_bad', EventStatus::Failed);
    outboxRow(EventType::Refund, 'refund:in_bad:total', EventStatus::Failed);

    $this->actingAs(customer())->get('/boraindicar?status=failed&type=payment')
        ->assertOk()
        ->assertSee('payment:in_bad')
        ->assertDontSee('payment:in_ok')
        ->assertDontSee('refund:in_bad:total');

    $this->get('/boraindicar?status=bogus')->assertSessionHasErrors('status');
});

it('resends a failed event and all failed events', function () {
    Gate::define('viewBoraIndicar', fn () => true);
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);
    $one = outboxRow(EventType::Payment, 'payment:in_bad', EventStatus::Failed);
    $two = outboxRow(EventType::Refund, 'refund:in_bad:total', EventStatus::Failed);
    $this->actingAs(customer());

    $this->post("/boraindicar/events/{$one->id}/retry")->assertRedirect();
    expect($one->refresh()->status)->toBe(EventStatus::Sent)->and($two->refresh()->status)->toBe(EventStatus::Failed);

    $this->post('/boraindicar/events/retry-failed')->assertRedirect()->assertSessionHas('boraindicar.flash', '1 evento(s) reenviado(s).');
    expect($two->refresh()->status)->toBe(EventStatus::Sent);

    $this->post("/boraindicar/events/{$one->id}/retry")->assertStatus(409);
});

it('tests the connection and shows the result', function () {
    Gate::define('viewBoraIndicar', fn () => true);
    Http::fake(['hub.test/api/v1/ping' => Http::response(['product' => ['slug' => 'exemplo', 'name' => 'Exemplo SaaS']])]);

    $this->actingAs(customer())->post('/boraindicar/ping')->assertRedirect('/boraindicar')->assertSessionHas('boraindicar.flash', 'Conexão com o hub OK.');

    expect(app(PingHub::class)->last())->toMatchArray(['ok' => true, 'product' => ['slug' => 'exemplo', 'name' => 'Exemplo SaaS']]);
    $this->get('/boraindicar')->assertSee('Exemplo SaaS');
});
