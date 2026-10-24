<?php

namespace BoraIndicar\Laravel;

use BoraIndicar\Laravel\Console\InstallCommand;
use BoraIndicar\Laravel\Console\PingCommand;
use BoraIndicar\Laravel\Console\RetryCommand;
use BoraIndicar\Laravel\Contracts\ProvidesCoupons;
use BoraIndicar\Laravel\Contracts\ResolvesInvoice;
use BoraIndicar\Laravel\Http\Middleware\CaptureReferral;
use BoraIndicar\Laravel\Listeners\RecordLeadOnRegistered;
use BoraIndicar\Laravel\Listeners\StripeWebhookListener;
use BoraIndicar\Laravel\Stripe\StripeCoupons;
use BoraIndicar\Laravel\Stripe\StripeInvoiceResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;

class BoraIndicarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/boraindicar.php', 'boraindicar');

        $this->app->singleton(BoraIndicarManager::class);
        $this->app->alias(BoraIndicarManager::class, 'boraindicar');

        $this->app->singleton(HubClient::class, fn ($app) => new HubClient(
            $app->make(HttpFactory::class),
            (string) $app['config']->get('boraindicar.url'),
            $app['config']->get('boraindicar.key'),
            $app['config']->get('boraindicar.secret'),
            (int) $app['config']->get('boraindicar.timeout', 5),
        ));

        if (class_exists(WebhookReceived::class)) {
            $this->app->bind(ResolvesInvoice::class, StripeInvoiceResolver::class);
            $this->app->bind(ProvidesCoupons::class, StripeCoupons::class);
        }
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/boraindicar.php' => config_path('boraindicar.php')], 'boraindicar-config');
        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'boraindicar-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/boraindicar')], 'boraindicar-views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'boraindicar');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // The hub snippet and this server share the same plain JSON cookie.
        EncryptCookies::except([$this->app['config']->get('boraindicar.cookie.name')]);

        $this->registerMiddleware();
        $this->registerListeners();
        $this->registerGate();

        Blade::directive('boraindicarScript', fn () => '<?php echo app(\\BoraIndicar\\Laravel\\BoraIndicarManager::class)->scriptTag(); ?>');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PingCommand::class, RetryCommand::class]);
        }
    }

    private function registerMiddleware(): void
    {
        $group = $this->app['config']->get('boraindicar.middleware_group');

        if (blank($group)) {
            return;
        }

        // Through the kernel: it re-syncs its groups to the router, which would drop a router-only push.
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof FoundationKernel && array_key_exists($group, $kernel->getMiddlewareGroups())) {
            $kernel->appendMiddlewareToGroup($group, CaptureReferral::class);
        }
    }

    private function registerListeners(): void
    {
        $events = $this->app->make(Events::class);

        if ($this->app['config']->get('boraindicar.auto.registered')) {
            $events->listen(Registered::class, RecordLeadOnRegistered::class);
        }

        if ($this->app['config']->get('boraindicar.auto.cashier') && class_exists(WebhookReceived::class)) {
            $events->listen(WebhookReceived::class, StripeWebhookListener::class);
        }
    }

    /** Runs after every provider booted, so the app's own Gate::define('viewBoraIndicar') wins. */
    private function registerGate(): void
    {
        $this->app->booted(function () {
            $gate = $this->app->make(Gate::class);

            if (! $gate->has('viewBoraIndicar')) {
                $gate->define('viewBoraIndicar', fn ($user = null) => $this->app->environment('local'));
            }
        });
    }
}
