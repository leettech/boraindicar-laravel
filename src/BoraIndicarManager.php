<?php

namespace BoraIndicar\Laravel;

use BoraIndicar\Laravel\Actions\QueueDelivery;
use BoraIndicar\Laravel\Contracts\ProvidesCoupons;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Jobs\SendEvent;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Writes facts to the outbox (boraindicar_events) and queues their delivery.
 * Every method is idempotent by its idempotency key and returns null when
 * the integration is disabled.
 */
class BoraIndicarManager
{
    public const SESSION_KEY = 'boraindicar.attribution';

    private ?Closure $customerIdResolver = null;

    public function __construct(
        private readonly Container $container,
        private readonly ConfigRepository $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('boraindicar.enabled');
    }

    /** Valid, non-expired attribution from the cookie, then the session, then the query string. */
    public function attribution(?Request $request = null): ?Attribution
    {
        $request ??= $this->container->make('request');

        return $this->storedAttribution($request)
            ?? $this->valid(Attribution::fromRequest($request, $this->config->get('boraindicar.query_params')));
    }

    /** Valid, non-expired attribution already captured (cookie, then session). */
    public function storedAttribution(Request $request): ?Attribution
    {
        $cookie = $request->cookie($this->config->get('boraindicar.cookie.name'));
        $stored = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return $this->valid(Attribution::fromCookie(is_string($cookie) ? $cookie : null))
            ?? $this->valid(is_array($stored) ? Attribution::fromArray($stored) : null);
    }

    public function forgetAttribution(): void
    {
        $this->container->make(CookieJar::class)->queue(
            $this->container->make(CookieJar::class)->forget(
                $this->config->get('boraindicar.cookie.name'),
                '/',
                $this->config->get('boraindicar.cookie.domain'),
            ),
        );

        $request = $this->container->make('request');

        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }

    /** @param array<string, string> $utm */
    public function click(string $code, ?string $landingUrl, ?string $referer, array $utm = []): ?BoraIndicarEvent
    {
        return $this->record(EventType::Click, 'click:'.Str::ulid(), [
            'code' => $code,
            'landing_url' => $landingUrl === null ? null : Str::limit($landingUrl, 2000, ''),
            'referer' => $referer === null ? null : Str::limit($referer, 2000, ''),
            'utm' => $utm,
        ]);
    }

    /**
     * @param  Authenticatable|array<string, mixed>  $customer
     */
    public function lead(Authenticatable|array $customer, ?Attribution $attribution = null): ?BoraIndicarEvent
    {
        $attribution ??= $this->attribution();

        if ($attribution === null) {
            return null;
        }

        $customerId = $this->customerId($customer);
        $signedUpAt = data_get($customer, 'created_at');

        return $this->record(EventType::Lead, "lead:{$customerId}", [
            'external_id' => $customerId,
            'email' => data_get($customer, 'email'),
            'name' => data_get($customer, 'name'),
            'code' => $attribution->code,
            'click_id' => $attribution->clickId,
            'utm' => $attribution->utm,
            'signed_up_at' => ($signedUpAt === null ? CarbonImmutable::now() : CarbonImmutable::parse($signedUpAt))
                ->utc()->toIso8601String(),
        ], afterCreate: fn () => BoraIndicarReferral::query()->firstOrCreate(['customer_id' => $customerId], [
            'code' => $attribution->code,
            'click_id' => $attribution->clickId,
            'utm' => $attribution->utm,
        ]));
    }

    public function payment(
        string|int $customerId,
        string $paymentId,
        int $amountCents,
        ?string $currency = 'BRL',
        ?CarbonInterface $paidAt = null,
    ): ?BoraIndicarEvent {
        return $this->record(EventType::Payment, "payment:{$paymentId}", [
            'customer_external_id' => (string) $customerId,
            'external_id' => $paymentId,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency ?? 'BRL'),
            'paid_at' => CarbonImmutable::instance($paidAt ?? CarbonImmutable::now())->utc()->toIso8601String(),
        ]);
    }

    /** Null amount means a full refund; a partial one carries the cumulative refunded amount. */
    public function refund(string $paymentId, ?int $amountCents = null): ?BoraIndicarEvent
    {
        $payload = ['payment_external_id' => $paymentId];

        if ($amountCents !== null) {
            $payload['amount_cents'] = $amountCents;
        }

        return $this->record(EventType::Refund, "refund:{$paymentId}:".($amountCents ?? 'total'), $payload);
    }

    /** The reference (e.g. subscription id) lets a later cancel of the same customer be reported again. */
    public function cancel(Model|Authenticatable|string|int $customer, ?string $reference = null): ?BoraIndicarEvent
    {
        $customerId = $this->customerId($customer);
        $key = $reference === null ? "cancel:{$customerId}" : "cancel:{$customerId}:{$reference}";

        return $this->record(EventType::Cancel, $key, ['external_id' => $customerId]);
    }

    /**
     * The discount the hub promised to this customer, to show and apply at
     * checkout. A lead still in the queue is delivered now (its answer
     * carries the discount); one sent before the discount existed is asked
     * to the hub once. Never throws: a hub error is reported and means no
     * discount, stored as nothing so a later call asks again.
     *
     * @param  Model|Authenticatable|array<string, mixed>|string|int  $customer
     */
    public function discount(Model|Authenticatable|array|string|int $customer): ?Discount
    {
        $referral = BoraIndicarReferral::query()->where('customer_id', $this->customerId($customer))->first();

        if ($referral === null) {
            return null;
        }

        if (! $referral->knowsDiscount() && $this->enabled()) {
            try {
                $this->refreshFromHub($referral);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }

        return $referral->discount();
    }

    /**
     * Id of a Stripe coupon for the customer's discount (created on first
     * use), for the checkout session's "discounts". Null without a discount
     * or when Stripe fails, so the checkout goes on without it.
     *
     * @param  Model|Authenticatable|array<string, mixed>|string|int  $customer
     */
    public function stripeCouponId(Model|Authenticatable|array|string|int $customer): ?string
    {
        $discount = $this->discount($customer);

        if ($discount === null) {
            return null;
        }

        if (! $this->container->bound(ProvidesCoupons::class)) {
            throw new LogicException('BoraIndicar::stripeCouponId precisa do laravel/cashier.');
        }

        try {
            return $this->container->make(ProvidesCoupons::class)->ensure($discount);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  Model|Authenticatable|array<string, mixed>|string|int  $customer
     */
    public function customerId(Model|Authenticatable|array|string|int $customer): string
    {
        if (is_string($customer) || is_int($customer)) {
            return (string) $customer;
        }

        if ($this->customerIdResolver !== null) {
            return (string) ($this->customerIdResolver)($customer);
        }

        return (string) data_get($customer, $this->config->get('boraindicar.customer_id', 'id'));
    }

    public function resolveCustomerIdUsing(Closure $callback): void
    {
        $this->customerIdResolver = $callback;
    }

    public function scriptTag(): HtmlString
    {
        $key = $this->config->get('boraindicar.key');

        if (blank($key)) {
            return new HtmlString('');
        }

        $src = rtrim((string) $this->config->get('boraindicar.url'), '/').'/js/boraindicar.js';
        $domain = $this->config->get('boraindicar.cookie.domain');
        $domainAttribute = blank($domain) ? '' : ' data-domain="'.e($domain).'"';

        return new HtmlString('<script src="'.e($src).'" data-key="'.e($key).'"'.$domainAttribute.' defer></script>');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function record(EventType $type, string $idempotencyKey, array $payload, ?Closure $afterCreate = null): ?BoraIndicarEvent
    {
        if (! $this->enabled()) {
            return null;
        }

        // firstOrCreate inserts under a savepoint when a transaction is open and
        // re-reads the row after a unique violation, so a race is a no-op.
        $event = BoraIndicarEvent::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            ['type' => $type, 'payload' => $payload],
        );

        if (! $event->wasRecentlyCreated) {
            return $event;
        }

        if ($afterCreate !== null) {
            $afterCreate();
        }

        $this->dispatch($event);

        return $event;
    }

    /**
     * A queue that is down (or the sync driver with the hub down) must not
     * break the caller's sign-up or webhook: the row stays pending and can be
     * resent from the dashboard or with boraindicar:retry.
     */
    private function dispatch(BoraIndicarEvent $event): void
    {
        try {
            $this->container->make(QueueDelivery::class)->handle($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * A pending lead goes out first, with the checkout's timeouts (SendEvent
     * stores the hub's answer). Then a 404 means the hub has no referral for
     * the customer (the lead was refused): stored as "no discount".
     */
    private function refreshFromHub(BoraIndicarReferral $referral): void
    {
        $hub = $this->container->make(HubClient::class)->duringCheckout();
        $lead = BoraIndicarEvent::query()
            ->ofType(EventType::Lead)
            ->withStatus(EventStatus::Pending)
            ->where('idempotency_key', "lead:{$referral->customer_id}")
            ->first();

        if ($lead !== null) {
            (new SendEvent($lead->id))->handle($hub, $this);

            if ($referral->refresh()->knowsDiscount()) {
                return;
            }
        }

        $response = $hub->referralFor($referral->customer_id);

        if ($response->notFound()) {
            $referral->update(['discount_bp' => 0, 'discount_months' => null]);

            return;
        }

        $hubReferral = $response->json('referral');

        if (! $response->successful() || ! is_array($hubReferral)) {
            throw new RuntimeException("boraindicar: o hub respondeu HTTP {$response->status()} ao consultar o cliente {$referral->customer_id}.");
        }

        $referral->update(BoraIndicarReferral::columnsFromHub($hubReferral));
    }

    private function valid(?Attribution $attribution): ?Attribution
    {
        return $attribution !== null && ! $attribution->isExpired((int) $this->config->get('boraindicar.cookie.days', 90))
            ? $attribution
            : null;
    }
}
