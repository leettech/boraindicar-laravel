<?php

namespace BoraIndicar\Laravel\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'boraindicar:install {--force : Sobrescreve arquivos já publicados}';

    protected $description = 'Publica a config e as migrations do boraindicar e mostra o que configurar';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'boraindicar-config', '--force' => $this->option('force')]);
        $this->call('vendor:publish', ['--tag' => 'boraindicar-migrations', '--force' => $this->option('force')]);

        $this->newLine();
        $this->components->info('Adicione ao .env (chaves em Time > Produtos > Chaves de API no hub):');
        $this->line('BORAINDICAR_URL=https://boraindicar.com');
        $this->line('BORAINDICAR_KEY=pk_...');
        $this->line('BORAINDICAR_SECRET=sk_...');
        $this->line('BORAINDICAR_COOKIE_DOMAIN=.seudominio.com.br');

        $this->newLine();
        $this->components->info('Rode as migrations:');
        $this->line('php artisan migrate');

        $this->newLine();
        $this->components->info('Snippet do hub para landings (no <head>). Em Blade:');
        $this->line('@boraindicarScript');
        $this->components->info('Em HTML estático, a tag equivalente:');
        $this->line('<script src="'.rtrim((string) config('boraindicar.url'), '/').'/js/boraindicar.js" data-key="pk_..." data-domain=".seudominio.com.br" defer></script>');

        $this->newLine();
        $this->components->info('Teste a conexão:');
        $this->line('php artisan boraindicar:ping');

        return self::SUCCESS;
    }
}
