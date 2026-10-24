<?php

namespace BoraIndicar\Laravel\Actions;

use BoraIndicar\Laravel\Jobs\SendEvent;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Closure;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Throwable;

/**
 * Queues SendEvent under its unique lock, like PendingDispatch does, but
 * tells the caller whether it was queued: false means a delivery of this
 * event is already waiting in the queue.
 */
final class QueueDelivery
{
    public function __construct(
        private readonly Dispatcher $bus,
        private readonly Cache $cache,
        private readonly ConfigRepository $config,
    ) {}

    /** @param (Closure(): void)|null $beforeDispatch runs only once the lock is held */
    public function handle(BoraIndicarEvent $event, ?Closure $beforeDispatch = null): bool
    {
        $job = (new SendEvent($event->id))
            ->onConnection($this->config->get('boraindicar.queue.connection'))
            ->onQueue($this->config->get('boraindicar.queue.queue'))
            ->afterCommit();

        $lock = new UniqueLock($this->cache);

        if (! $lock->acquire($job)) {
            return false;
        }

        if ($beforeDispatch !== null) {
            $beforeDispatch();
        }

        try {
            $this->bus->dispatch($job);
        } catch (Throwable $e) {
            $lock->release($job);

            throw $e;
        }

        return true;
    }
}
