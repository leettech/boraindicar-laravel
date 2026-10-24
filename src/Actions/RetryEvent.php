<?php

namespace BoraIndicar\Laravel\Actions;

use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;

/** Manual resend: back to pending with a fresh attempt budget. */
final class RetryEvent
{
    public function __construct(private readonly QueueDelivery $queue) {}

    /** False when a delivery of this event is already queued (nothing changed). */
    public function handle(BoraIndicarEvent $event): bool
    {
        return $this->queue->handle($event, fn () => $event->resetForRetry());
    }

    /** Resends every failed event; returns how many were queued. */
    public function handleFailed(): int
    {
        $count = 0;

        BoraIndicarEvent::query()->withStatus(EventStatus::Failed)->chunkById(100, function ($events) use (&$count) {
            foreach ($events as $event) {
                $count += (int) $this->handle($event);
            }
        });

        return $count;
    }
}
