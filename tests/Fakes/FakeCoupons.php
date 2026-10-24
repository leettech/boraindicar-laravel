<?php

namespace Tests\Fakes;

use BoraIndicar\Laravel\Contracts\ProvidesCoupons;
use BoraIndicar\Laravel\Discount;

final class FakeCoupons implements ProvidesCoupons
{
    /** @var list<Discount> */
    public array $ensured = [];

    public function ensure(Discount $discount): string
    {
        $this->ensured[] = $discount;

        return $discount->couponId();
    }
}
