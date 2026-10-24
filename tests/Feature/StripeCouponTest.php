<?php

use BoraIndicar\Laravel\Contracts\ProvidesCoupons;
use BoraIndicar\Laravel\Discount;
use BoraIndicar\Laravel\Facades\BoraIndicar;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use BoraIndicar\Laravel\Stripe\StripeCoupons;
use Illuminate\Support\Facades\Exceptions;
use Stripe\ApiRequestor;
use Stripe\Exception\InvalidRequestException;
use Tests\Fakes\FakeCoupons;
use Tests\Fakes\FakeStripeHttpClient;

function discountedReferral(int $bp = 1000, ?int $months = 12): void
{
    BoraIndicarReferral::query()->create([
        'customer_id' => '1', 'code' => 'mar7k2qp', 'attributed' => true,
        'discount_bp' => $bp, 'discount_months' => $months,
    ]);
}

function fakeStripe(array ...$responses): FakeStripeHttpClient
{
    config(['cashier.secret' => 'sk_test_stripe']);
    ApiRequestor::setHttpClient($client = new FakeStripeHttpClient($responses));

    return $client;
}

afterEach(fn () => ApiRequestor::setHttpClient(null));

it('returns the coupon for the customer discount', function () {
    app()->instance(ProvidesCoupons::class, $coupons = new FakeCoupons);
    discountedReferral();

    expect(BoraIndicar::stripeCouponId(customer()))->toBe('boraindicar-10pct-12m')
        ->and($coupons->ensured)->toEqual([new Discount(1000, 12)]);
});

it('touches no gateway without a discount', function () {
    app()->instance(ProvidesCoupons::class, $coupons = new FakeCoupons);
    discountedReferral(0, null);

    expect(BoraIndicar::stripeCouponId('1'))->toBeNull()
        ->and($coupons->ensured)->toBe([]);
});

it('needs Cashier to build a coupon', function () {
    app()->offsetUnset(ProvidesCoupons::class);
    discountedReferral();

    expect(fn () => BoraIndicar::stripeCouponId('1'))->toThrow(LogicException::class, 'BoraIndicar::stripeCouponId precisa do laravel/cashier.');
});

it('goes on without the coupon when Stripe fails', function () {
    Exceptions::fake();
    fakeStripe(FakeStripeHttpClient::error(401, 'api_key_invalid'));
    discountedReferral();

    expect(BoraIndicar::stripeCouponId('1'))->toBeNull();
    Exceptions::assertReportedCount(1);
});

it('creates the coupon the first time', function () {
    $stripe = fakeStripe(FakeStripeHttpClient::error(404, 'resource_missing'), FakeStripeHttpClient::coupon('boraindicar-12_5pct-3m'));

    expect(app(StripeCoupons::class)->ensure(new Discount(1250, 3)))->toBe('boraindicar-12_5pct-3m')
        ->and($stripe->requests)->toBe([
            ['method' => 'get', 'path' => '/v1/coupons/boraindicar-12_5pct-3m', 'params' => []],
            ['method' => 'post', 'path' => '/v1/coupons', 'params' => [
                'id' => 'boraindicar-12_5pct-3m',
                'name' => 'boraindicar: 12,5% por 3 meses',
                'percent_off' => 12.5,
                'duration' => 'repeating',
                'duration_in_months' => 3,
            ]],
        ]);
});

it('reuses a coupon that already exists', function () {
    $stripe = fakeStripe(FakeStripeHttpClient::coupon('boraindicar-10pct-12m'));

    expect(app(StripeCoupons::class)->ensure(new Discount(1000, 12)))->toBe('boraindicar-10pct-12m')
        ->and($stripe->requests)->toHaveCount(1);
});

it('accepts a coupon another checkout created in between', function () {
    $stripe = fakeStripe(
        FakeStripeHttpClient::error(404, 'resource_missing'),
        FakeStripeHttpClient::error(400, 'resource_already_exists'),
        FakeStripeHttpClient::coupon('boraindicar-10pct-12m'),
    );

    expect(app(StripeCoupons::class)->ensure(new Discount(1000, 12)))->toBe('boraindicar-10pct-12m')
        ->and(array_column($stripe->requests, 'method'))->toBe(['get', 'post', 'get']);
});

it('does not create over an error other than a missing coupon', function () {
    $stripe = fakeStripe(FakeStripeHttpClient::error(400, 'parameter_invalid'));

    expect(fn () => app(StripeCoupons::class)->ensure(new Discount(1000, 12)))->toThrow(InvalidRequestException::class)
        ->and($stripe->requests)->toHaveCount(1);
});
