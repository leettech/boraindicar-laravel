<?php

namespace Tests\Fakes;

use RuntimeException;
use Stripe\HttpClient\ClientInterface;

/** Answers Stripe API calls from a queue and records them (method, path, params). */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, path: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @param list<array{0: array<string, mixed>, 1: int}> $responses body and status, in order */
    public function __construct(private array $responses) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'path' => (string) parse_url($absUrl, PHP_URL_PATH), 'params' => $params];

        [$body, $status] = array_shift($this->responses) ?? throw new RuntimeException("Unexpected Stripe request: {$method} {$absUrl}");

        return [json_encode($body, JSON_THROW_ON_ERROR), $status, []];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    public static function coupon(string $id): array
    {
        return [['id' => $id, 'object' => 'coupon'], 200];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    public static function error(int $status, string $code): array
    {
        return [['error' => ['type' => 'invalid_request_error', 'code' => $code, 'message' => $code]], $status];
    }
}
