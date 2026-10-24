<?php

namespace BoraIndicar\Laravel;

use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * HTTP client for the hub API (/api/v1). Retries connection errors and 5xx a
 * few times in-process; durable retry is the queue's job. The copy from
 * duringCheckout() asks once with short timeouts, for calls the customer
 * waits on.
 */
class HubClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $url,
        private readonly ?string $key,
        private readonly ?string $secret,
        private readonly int $timeout = 5,
        private readonly bool $checkout = false,
    ) {}

    /** No in-process retry, 1s to connect and 2s in all: a slow hub means no discount, not a stuck checkout. */
    public function duringCheckout(): self
    {
        return new self($this->http, $this->url, $this->key, $this->secret, $this->timeout, checkout: true);
    }

    public function ping(): Response
    {
        return $this->request()->get('ping');
    }

    public function send(BoraIndicarEvent $event): Response
    {
        return match ($event->type) {
            EventType::Click => $this->click($event->payload),
            EventType::Lead => $this->referral($event->payload),
            EventType::Payment => $this->payment($event->payload),
            EventType::Refund => $this->refund($event->payload),
            EventType::Cancel => $this->cancel((string) $event->payload['external_id']),
        };
    }

    /** @param array<string, mixed> $body */
    public function click(array $body): Response
    {
        return $this->request(publicKey: true)->post('clicks', $body);
    }

    /** @param array<string, mixed> $body */
    public function referral(array $body): Response
    {
        return $this->request()->post('referrals', $body);
    }

    /** @param array<string, mixed> $body */
    public function payment(array $body): Response
    {
        return $this->request()->post('payments', $body);
    }

    /** @param array<string, mixed> $body */
    public function refund(array $body): Response
    {
        return $this->request()->post('refunds', $body);
    }

    /** The hub's view of one customer: 200 {referral: {id, status, attributed, discount}} or 404. */
    public function referralFor(string $externalId): Response
    {
        return $this->duringCheckout()->request()->get('referrals/'.rawurlencode($externalId));
    }

    public function cancel(string $externalId): Response
    {
        return $this->request()->post('referrals/'.rawurlencode($externalId).'/cancel');
    }

    private function request(bool $publicKey = false): PendingRequest
    {
        $request = $this->http
            ->baseUrl(rtrim($this->url, '/').'/api/v1')
            ->acceptJson()
            ->asJson();

        $request = $this->checkout
            ? $request->timeout(2)->connectTimeout(1)
            : $request->timeout($this->timeout)->connectTimeout(3)
                ->retry(3, 250, fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false);

        return $publicKey
            ? $request->withHeaders(['X-BoraIndicar-Key' => (string) $this->key])
            : $request->withToken((string) $this->secret);
    }
}
