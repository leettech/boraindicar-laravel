<?php

namespace BoraIndicar\Laravel\Actions;

use BoraIndicar\Laravel\HubClient;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;

/**
 * GET /ping and remember the outcome for the dashboard.
 *
 * Result: {ok: bool, at: ISO-8601, product: {slug, name}|null, error: string|null}.
 */
final class PingHub
{
    public const CACHE_KEY = 'boraindicar:last_ping';

    public function __construct(
        private readonly HubClient $hub,
        private readonly Cache $cache,
    ) {}

    /** @return array{ok: bool, at: string, product: array{slug?: string, name?: string}|null, error: string|null} */
    public function handle(): array
    {
        try {
            $response = $this->hub->ping();
            $ok = $response->successful();
            $error = $ok ? null : "HTTP {$response->status()}: ".($response->json('message') ?: $response->reason());
            $product = $ok ? $response->json('product') : null;
        } catch (ConnectionException $e) {
            [$ok, $error, $product] = [false, $e->getMessage(), null];
        }

        $result = [
            'ok' => $ok,
            'at' => now()->toIso8601String(),
            'product' => is_array($product) ? $product : null,
            'error' => $error,
        ];

        $this->cache->forever(self::CACHE_KEY, $result);

        return $result;
    }

    /** @return array{ok: bool, at: string, product: array{slug?: string, name?: string}|null, error: string|null}|null */
    public function last(): ?array
    {
        $last = $this->cache->get(self::CACHE_KEY);

        return is_array($last) ? $last : null;
    }
}
