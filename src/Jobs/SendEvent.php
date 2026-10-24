<?php

namespace BoraIndicar\Laravel\Jobs;

use BoraIndicar\Laravel\BoraIndicarManager;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Enums\EventType;
use BoraIndicar\Laravel\Exceptions\HubUnavailableException;
use BoraIndicar\Laravel\HubClient;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use BoraIndicar\Laravel\Models\BoraIndicarReferral;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Delivers one outbox row. 5xx/connection errors are retried by the queue
 * with exponential backoff; any other 4xx (422 included) fails right away,
 * because resending the same data gives the same answer.
 */
final class SendEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    /** Safety net: a lock left by a lost job frees itself after an hour. */
    public int $uniqueFor = 3600;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600, 1200, 1800, 3600, 3600];
    }

    public function handle(HubClient $hub, BoraIndicarManager $boraindicar): void
    {
        $event = BoraIndicarEvent::query()->find($this->eventId);

        if ($event === null || $event->status === EventStatus::Sent || ! $boraindicar->enabled()) {
            return;
        }

        $event->increment('attempts');

        try {
            $response = $hub->send($event);
        } catch (ConnectionException $e) {
            $event->markRetrying($e->getMessage());

            throw new HubUnavailableException($e->getMessage(), previous: $e);
        }

        if ($response->successful()) {
            $event->markSent(self::body($response));
            $this->afterSent($event, $response);

            return;
        }

        if ($response->serverError()) {
            $event->markRetrying(self::describe($response));

            throw new HubUnavailableException(self::describe($response));
        }

        $event->markFailed(self::describe($response), self::body($response));
    }

    public function failed(?Throwable $exception): void
    {
        BoraIndicarEvent::query()->find($this->eventId)?->markFailed(
            $exception?->getMessage() ?: 'Tentativas esgotadas',
        );
    }

    private function afterSent(BoraIndicarEvent $event, Response $response): void
    {
        if ($event->type !== EventType::Lead) {
            return;
        }

        $referral = $response->json('referral');

        BoraIndicarReferral::query()
            ->where('customer_id', (string) $event->payload['external_id'])
            ->update(BoraIndicarReferral::columnsFromHub(is_array($referral) ? $referral : [
                'attributed' => $response->json('attributed'),
            ]));
    }

    /** @return array<array-key, mixed>|null */
    private static function body(Response $response): ?array
    {
        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private static function describe(Response $response): string
    {
        $message = $response->json('message');
        $errors = collect((array) $response->json('errors'))->flatten()->filter(fn ($error) => is_string($error));

        $detail = $errors->isNotEmpty()
            ? $errors->implode(' ')
            : (is_string($message) && $message !== '' ? $message : $response->reason());

        return "HTTP {$response->status()}: {$detail}";
    }
}
