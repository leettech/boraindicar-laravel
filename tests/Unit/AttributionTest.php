<?php

use BoraIndicar\Laravel\Attribution;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('reads ref, ix and only utm_* params from the request', function () {
    CarbonImmutable::setTestNow('2026-03-01 12:00:00');

    $attribution = Attribution::fromRequest(Request::create('/', 'GET', [
        'ref' => 'mar7k2qp',
        'ix' => '01JBX7Z8Q9R0S1T2V3W4X5Y6Z7',
        'utm_source' => 'instagram',
        'utm_campaign' => 'lancamento',
        'gclid' => 'ignored',
    ]));

    expect($attribution->code)->toBe('mar7k2qp')
        ->and($attribution->clickId)->toBe('01JBX7Z8Q9R0S1T2V3W4X5Y6Z7')
        ->and($attribution->utm)->toBe(['utm_source' => 'instagram', 'utm_campaign' => 'lancamento'])
        ->and($attribution->capturedAt->toDateTimeString())->toBe('2026-03-01 12:00:00');
});

it('accepts via as the code param and drops a malformed click id', function () {
    $attribution = Attribution::fromRequest(Request::create('/', 'GET', ['via' => 'ze4h9wnc', 'ix' => 'not-a-ulid']));

    expect($attribution->code)->toBe('ze4h9wnc')->and($attribution->clickId)->toBeNull();
});

it('returns null without a valid code', function (array $query) {
    expect(Attribution::fromRequest(Request::create('/', 'GET', $query)))->toBeNull();
})->with([
    'no code' => [['utm_source' => 'x']],
    'empty' => [['ref' => '']],
    'injection' => [['ref' => '<script>']],
    'array' => [['ref' => ['a']]],
]);

it('round-trips through the cookie value', function () {
    $original = new Attribution('mar7k2qp', null, ['utm_source' => 'x'], CarbonImmutable::createFromTimestampUTC(1767225600));

    $restored = Attribution::fromCookie($original->toCookieValue());

    expect($original->toCookieValue())->toBe('{"c":"mar7k2qp","k":null,"t":1767225600,"u":{"utm_source":"x"}}')
        ->and($restored)->toEqual($original);
});

it('rejects garbage cookies', function (?string $value) {
    expect(Attribution::fromCookie($value))->toBeNull();
})->with([null, '', 'not json', '"string"', '{"k":"01JBX7Z8Q9R0S1T2V3W4X5Y6Z7"}', '{"c":"<b>"}']);

it('expires after the configured days', function () {
    $attribution = new Attribution('mar7k2qp', null, [], CarbonImmutable::parse('2026-01-01 00:00:00'));

    expect($attribution->isExpired(90, CarbonImmutable::parse('2026-04-01 00:00:00')))->toBeFalse()
        ->and($attribution->isExpired(90, CarbonImmutable::parse('2026-04-01 00:00:01')))->toBeTrue();
});
