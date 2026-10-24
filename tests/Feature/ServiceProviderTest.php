<?php

use BoraIndicar\Laravel\Contracts\ProvidesCoupons;
use BoraIndicar\Laravel\Http\Middleware\CaptureReferral;
use BoraIndicar\Laravel\Stripe\StripeCoupons;
use Illuminate\Contracts\Http\Kernel;

function groupMiddleware(string $group): array
{
    return app(Kernel::class)->getMiddlewareGroups()[$group] ?? [];
}

it('appends CaptureReferral to the web group', function () {
    expect(groupMiddleware('web'))->toContain(CaptureReferral::class);
});

it('skips a middleware group that does not exist', function () {
    $this->rebootWith(['boraindicar.middleware_group' => 'storefront']);

    expect(app(Kernel::class)->getMiddlewareGroups())->not->toHaveKey('storefront')
        ->and(groupMiddleware('web'))->not->toContain(CaptureReferral::class);
});

it('registers no middleware when the group is null', function () {
    $this->rebootWith(['boraindicar.middleware_group' => null]);

    expect(groupMiddleware('web'))->not->toContain(CaptureReferral::class);
});

it('provides Stripe coupons when Cashier is installed', function () {
    expect(app(ProvidesCoupons::class))->toBeInstanceOf(StripeCoupons::class);
});
