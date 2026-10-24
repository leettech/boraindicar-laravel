<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Cashier::useCustomerModel(User::class);
        $this->app['config']->set('boraindicar.user_model', User::class);
        $this->app['config']->set('auth.providers.users.model', User::class);

        Gate::define('viewBoraIndicar', fn () => true);
    }
}
