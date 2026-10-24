<?php

namespace BoraIndicar\Laravel\Console;

use BoraIndicar\Laravel\Actions\RetryEvent;
use BoraIndicar\Laravel\Enums\EventStatus;
use BoraIndicar\Laravel\Models\BoraIndicarEvent;
use Illuminate\Console\Command;

class RetryCommand extends Command
{
    protected $signature = 'boraindicar:retry
        {id? : ID do evento em boraindicar_events}
        {--all : Reenvia todos com status failed}';

    protected $description = 'Reenvia eventos do boraindicar ao hub';

    public function handle(RetryEvent $retry): int
    {
        if ($this->option('all')) {
            $count = $retry->handleFailed();

            $this->components->info("{$count} evento(s) reenviado(s).");

            return self::SUCCESS;
        }

        if ($this->argument('id') === null) {
            $this->components->error('Informe o ID do evento ou use --all.');

            return self::FAILURE;
        }

        $event = BoraIndicarEvent::query()->find($this->argument('id'));

        if ($event === null) {
            $this->components->error("Evento {$this->argument('id')} não encontrado.");

            return self::FAILURE;
        }

        if ($event->status === EventStatus::Sent) {
            $this->components->warn("Evento {$event->id} já foi enviado.");

            return self::SUCCESS;
        }

        if (! $retry->handle($event)) {
            $this->components->warn("Evento {$event->id} já está na fila de envio.");

            return self::SUCCESS;
        }

        $this->components->info("Evento {$event->id} reenviado.");

        return self::SUCCESS;
    }
}
