<?php

namespace BoraIndicar\Laravel\Facades;

use BoraIndicar\Laravel\BoraIndicarManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled()
 * @method static \BoraIndicar\Laravel\Attribution|null attribution(\Illuminate\Http\Request|null $request = null)
 * @method static \BoraIndicar\Laravel\Attribution|null storedAttribution(\Illuminate\Http\Request $request)
 * @method static void forgetAttribution()
 * @method static \BoraIndicar\Laravel\Models\BoraIndicarEvent|null click(string $code, string|null $landingUrl, string|null $referer, array $utm = [])
 * @method static \BoraIndicar\Laravel\Models\BoraIndicarEvent|null lead(\Illuminate\Contracts\Auth\Authenticatable|array $customer, \BoraIndicar\Laravel\Attribution|null $attribution = null)
 * @method static \BoraIndicar\Laravel\Models\BoraIndicarEvent|null payment(string|int $customerId, string $paymentId, int $amountCents, string|null $currency = 'BRL', \Carbon\CarbonInterface|null $paidAt = null)
 * @method static \BoraIndicar\Laravel\Models\BoraIndicarEvent|null refund(string $paymentId, int|null $amountCents = null)
 * @method static \BoraIndicar\Laravel\Models\BoraIndicarEvent|null cancel(\Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Auth\Authenticatable|string|int $customer, string|null $reference = null)
 * @method static \BoraIndicar\Laravel\Discount|null discount(\Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Auth\Authenticatable|array|string|int $customer)
 * @method static string|null stripeCouponId(\Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Auth\Authenticatable|array|string|int $customer)
 * @method static string customerId(\Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Auth\Authenticatable|array|string|int $customer)
 * @method static void resolveCustomerIdUsing(\Closure $callback)
 * @method static \Illuminate\Support\HtmlString scriptTag()
 *
 * @see BoraIndicarManager
 */
class BoraIndicar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BoraIndicarManager::class;
    }
}
