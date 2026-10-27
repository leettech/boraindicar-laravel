<?php

use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Route::middleware('web')->get('/landing', fn () => 'ok');
    Route::middleware('web')->post('/landing', fn () => 'ok');
    Http::fake(['hub.test/api/v1/clicks' => Http::response(['click_id' => '01JBHUBCLICK0000000000000A'], 201)]);
    CarbonImmutable::setTestNow('2026-03-01 12:00:00');
});

function setCookieHeader(TestResponse $response): ?string
{
    return collect($response->headers->all('set-cookie'))->first(fn (string $header) => str_starts_with($header, 'boraindicar='));
}

it('stores a plain first-party cookie with the attribution', function () {
    $response = $this->get('/landing?ref=mar7k2qp&ix=01JBX7Z8Q9R0S1T2V3W4X5Y6Z7&utm_source=instagram');

    $response->assertOk()->assertPlainCookie('boraindicar', '{"c":"mar7k2qp","k":"01JBX7Z8Q9R0S1T2V3W4X5Y6Z7","t":1772366400,"u":{"utm_source":"instagram"}}');

    $header = setCookieHeader($response);
    expect($header)->toStartWith('boraindicar='.rawurlencode('{"c":"mar7k2qp","k":"01JBX7Z8Q9R0S1T2V3W4X5Y6Z7"'))
        ->toContain('domain=.exemplo.test')
        ->toContain('samesite=lax')
        ->not->toContain('httponly')
        ->and(session('boraindicar.attribution'))->toMatchArray(['c' => 'mar7k2qp']);
});

it('keeps the first touch when a valid cookie exists', function () {
    $first = '{"c":"first001","k":null,"t":'.CarbonImmutable::now()->subDays(10)->getTimestamp().',"u":{}}';

    $response = $this->withUnencryptedCookie('boraindicar', $first)->get('/landing?ref=second02&ix=01JBX7Z8Q9R0S1T2V3W4X5Y6Z7');

    expect(setCookieHeader($response))->toBeNull()
        ->and(session('boraindicar.attribution.c'))->toBe('first001');
    Http::assertNothingSent();
});

it('overwrites an expired cookie', function () {
    $expired = '{"c":"first001","k":null,"t":'.CarbonImmutable::now()->subDays(366)->getTimestamp().',"u":{}}';

    $this->withUnencryptedCookie('boraindicar', $expired)
        ->get('/landing?ref=second02&ix=01JBX7Z8Q9R0S1T2V3W4X5Y6Z7')
        ->assertPlainCookie('boraindicar', '{"c":"second02","k":"01JBX7Z8Q9R0S1T2V3W4X5Y6Z7","t":1772366400,"u":{}}');
});

it('records a click with the public key when ref comes without ix', function () {
    $this->get('/landing?ref=mar7k2qp&utm_source=instagram', ['referer' => 'https://instagram.com/'])->assertOk();

    $event = BoraIndicarEvent::query()->sole();
    expect($event->type)->toBe(EventType::Click)
        ->and($event->payload)->toBe([
            'code' => 'mar7k2qp',
            'landing_url' => 'http://localhost/landing?ref=mar7k2qp&utm_source=instagram',
            'referer' => 'https://instagram.com/',
            'utm' => ['utm_source' => 'instagram'],
        ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hub.test/api/v1/clicks'
        && $request->hasHeader('X-BoraIndicar-Key', 'pk_test')
        && ! $request->hasHeader('Authorization')
        && $request['code'] === 'mar7k2qp');
});

it('does not record a click when the hub already did (ix present)', function () {
    $this->get('/landing?ref=mar7k2qp&ix=01JBX7Z8Q9R0S1T2V3W4X5Y6Z7')->assertOk();

    expect(BoraIndicarEvent::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('ignores non-GET requests', function () {
    $response = $this->post('/landing?ref=mar7k2qp');

    expect(setCookieHeader($response))->toBeNull();
});

it('does nothing when disabled', function () {
    config(['boraindicar.enabled' => false]);

    $response = $this->get('/landing?ref=mar7k2qp');

    expect(setCookieHeader($response))->toBeNull()->and(BoraIndicarEvent::query()->count())->toBe(0);
});

it('reads only the configured query params, leaving the product own ref alone', function () {
    config(['boraindicar.query_params' => ['via']]);

    $response = $this->get('/landing?ref=t-amigo123')->assertOk();

    expect(setCookieHeader($response))->toBeNull()
        ->and(BoraIndicarEvent::query()->count())->toBe(0);

    $this->get('/landing?via=mar7k2qp&ix=01JBX7Z8Q9R0S1T2V3W4X5Y6Z7')
        ->assertPlainCookie('boraindicar', '{"c":"mar7k2qp","k":"01JBX7Z8Q9R0S1T2V3W4X5Y6Z7","t":1772366400,"u":{}}');
});

it('takes the query params from BORAINDICAR_QUERY_PARAMS, ref and via by default', function () {
    $config = fn () => (require __DIR__.'/../../config/boraindicar.php')['query_params'];

    expect($config())->toBe(['ref', 'via']);

    $_SERVER['BORAINDICAR_QUERY_PARAMS'] = $_ENV['BORAINDICAR_QUERY_PARAMS'] = ' via ';

    try {
        expect($config())->toBe(['via']);
    } finally {
        unset($_SERVER['BORAINDICAR_QUERY_PARAMS'], $_ENV['BORAINDICAR_QUERY_PARAMS']);
    }
});
