<?php

namespace BoraIndicar\Laravel\Console;

use BoraIndicar\Laravel\Actions\PingHub;
use Illuminate\Console\Command;

class PingCommand extends Command
{
    protected $signature = 'boraindicar:ping';

    protected $description = 'Testa a conexão com o hub do boraindicar';

    public function handle(PingHub $ping): int
    {
        $result = $ping->handle();

        if (! $result['ok']) {
            $this->components->error("Falha ao conectar no hub: {$result['error']}");

            return self::FAILURE;
        }

        $name = $result['product']['name'] ?? '?';
        $slug = $result['product']['slug'] ?? '?';
        $this->components->info("Conectado ao produto {$name} ({$slug}).");

        return self::SUCCESS;
    }
}
