<?php

namespace BoraIndicar\Laravel\Listeners;

use BoraIndicar\Laravel\BoraIndicarManager;
use Illuminate\Auth\Events\Registered;

/**
 * Runs in the sign-up request because the attribution lives in its cookie/session.
 * Only the customer model counts: apps also fire Registered for other models
 * (buyers, staff), whose ids would take the customer's "lead:{id}" key.
 */
final class RecordLeadOnRegistered
{
    public function __construct(private readonly BoraIndicarManager $boraindicar) {}

    public function handle(Registered $event): void
    {
        $model = $this->boraindicar->userModel();

        if ($model === null || ! $event->user instanceof $model) {
            return;
        }

        $attribution = $this->boraindicar->attribution();

        if ($attribution === null) {
            return;
        }

        $this->boraindicar->lead($event->user, $attribution);
        $this->boraindicar->forgetAttribution();
    }
}
