<?php

namespace BoraIndicar\Laravel\Listeners;

use BoraIndicar\Laravel\BoraIndicarManager;
use Illuminate\Auth\Events\Registered;

/** Runs in the sign-up request because the attribution lives in its cookie/session. */
final class RecordLeadOnRegistered
{
    public function __construct(private readonly BoraIndicarManager $boraindicar) {}

    public function handle(Registered $event): void
    {
        $attribution = $this->boraindicar->attribution();

        if ($attribution === null) {
            return;
        }

        $this->boraindicar->lead($event->user, $attribution);
        $this->boraindicar->forgetAttribution();
    }
}
