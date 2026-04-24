<?php

namespace PavanSaini\IdempotentWebhooks;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PavanSaini\IdempotentWebhooks\Http\Middleware\IdempotentMiddleware;

class IdempotentWebhooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/idempotent-webhooks.php',
            'idempotent-webhooks'
        );

        $this->app->singleton(Idempotency::class, function ($app) {
            return new Idempotency($app['config']['idempotent-webhooks']);
        });
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/idempotent-webhooks.php' => config_path('idempotent-webhooks.php'),
        ], 'idempotent-webhooks-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'idempotent-webhooks-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $router->aliasMiddleware('idempotent', IdempotentMiddleware::class);
    }
}
