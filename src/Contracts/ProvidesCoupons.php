<?php

namespace BoraIndicar\Laravel\Contracts;

use BoraIndicar\Laravel\Discount;

interface ProvidesCoupons
{
    /** Makes sure the gateway has a coupon for the discount and returns its id. */
    public function ensure(Discount $discount): string;
}
