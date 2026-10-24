<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();
        Sleep::fake();
    })
    ->in('Feature', 'Unit');

function customer(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'Ana Souza',
        'email' => 'ana@cliente.com.br',
        'password' => 'secret',
        ...$attributes,
    ]);
}

function stripeFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/stripe/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}
