<?php

namespace Tests;

use BoraIndicar\Laravel\BoraIndicarServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\User;

/**
 * Providers are listed here instead of WithWorkbench so the workbench's
 * "everyone sees the dashboard" gate does not leak into the tests.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** @var array<string, mixed> Config applied before the providers boot (see rebootWith). */
    private array $bootConfig = [];

    /** Re-creates the app with config the provider reads at boot time (fresh, unmigrated database). */
    protected function rebootWith(array $config): void
    {
        $this->bootConfig = $config;
        $this->refreshApplication();
    }

    protected function getPackageProviders($app): array
    {
        return [CashierServiceProvider::class, BoraIndicarServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('boraindicar.user_model', User::class);
        $app['config']->set('boraindicar.cookie.domain', '.exemplo.test');

        Cashier::useCustomerModel(User::class);

        foreach ($this->bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../database/migrations',
            __DIR__.'/../workbench/database/migrations',
        ]);
    }
}
