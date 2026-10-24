<?php

namespace BoraIndicar\Laravel\Http\Controllers;

use BoraIndicar\Laravel\Actions\RetryEvent;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Illuminate\Http\RedirectResponse;

class EventRetryController
{
    public function store(BoraIndicarEvent $event, RetryEvent $retry): RedirectResponse
    {
        abort_if($event->status === EventStatus::Sent, 409, 'Evento já enviado.');

        return back()->with('boraindicar.flash', $retry->handle($event)
            ? "Evento #{$event->id} reenviado."
            : "Evento #{$event->id} já está na fila de envio.");
    }

    public function all(RetryEvent $retry): RedirectResponse
    {
        $count = $retry->handleFailed();

        return back()->with('boraindicar.flash', "{$count} evento(s) reenviado(s).");
    }
}
