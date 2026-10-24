<?php

namespace BoraIndicar\Laravel\Http\Middleware;

use BoraIndicar\Laravel\Attribution;
use BoraIndicar\Laravel\BoraIndicarManager;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures ?ref/?via (+ ix and utm_*) into a first-party cookie, first-touch.
 * The cookie is plain JSON (not encrypted, not httpOnly) so the hub's JS
 * snippet on the same domain reads the same value instead of rewriting it.
 */
class CaptureReferral
{
    public function __construct(
        private readonly BoraIndicarManager $boraindicar,
        private readonly ConfigRepository $config,
        private readonly CookieJar $cookies,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $this->boraindicar->enabled()) {
            return $next($request);
        }

        $incoming = Attribution::fromRequest($request, $this->config->get('boraindicar.query_params'));

        if ($incoming === null) {
            return $next($request);
        }

        $existing = $this->boraindicar->storedAttribution($request);

        if ($existing !== null) {
            $this->remember($request, $existing);

            return $next($request);
        }

        $this->cookies->queue($this->cookies->make(
            $this->config->get('boraindicar.cookie.name'),
            $incoming->toCookieValue(),
            (int) $this->config->get('boraindicar.cookie.days') * 1440,
            '/',
            $this->config->get('boraindicar.cookie.domain'),
            null,
            false,
            false,
            'lax',
        ));

        $this->remember($request, $incoming);

        if ($incoming->clickId === null) {
            $this->boraindicar->click($incoming->code, $request->fullUrl(), $request->headers->get('referer'), $incoming->utm);
        }

        return $next($request);
    }

    private function remember(Request $request, Attribution $attribution): void
    {
        if ($request->hasSession()) {
            $request->session()->put(BoraIndicarManager::SESSION_KEY, $attribution->toArray());
        }
    }
}
