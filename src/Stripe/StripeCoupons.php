<?php

namespace BoraIndicar\Laravel\Stripe;

use BoraIndicar\Laravel\Contracts\ProvidesCoupons;
use BoraIndicar\Laravel\Discount;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

/**
 * One "N% for M months" coupon per combination, created on first use. It goes
 * on the checkout session: Cashier 16 no longer applies customer discounts,
 * and the first invoice already needs it.
 */
final class StripeCoupons implements ProvidesCoupons
{
    public function ensure(Discount $discount): string
    {
        $id = $discount->couponId();

        try {
            Cashier::stripe()->coupons->retrieve($id);

            return $id;
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() !== 404) {
                throw $e;
            }
        }

        try {
            Cashier::stripe()->coupons->create([
                'id' => $id,
                'name' => $discount->label(),
                'percent_off' => $discount->percent(),
                'duration' => 'repeating',
                'duration_in_months' => $discount->months,
            ]);
        } catch (InvalidRequestException) {
            // Another checkout may have created the same id in between; it existing is all that matters.
            Cashier::stripe()->coupons->retrieve($id);
        }

        return $id;
    }
}
