<?php

namespace Werk365\LaravelJsonApi;

use Illuminate\Support\ServiceProvider;
use Werk365\LaravelJsonApi\Middleware\ResourceHash;

class LaravelJsonApiServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'werk365');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'werk365');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');
        //Middlewares
        $middlewares = [
            ResourceHash::class,
        ];

        // Set middleware group
        $this->app['router']->middlewareGroup('jsonapi', $middlewares);

        // Set individual middlewares
        foreach ($middlewares as $middleware) {
            $this->app['router']->aliasMiddleware((new $middleware)->name(), $middleware);
        }

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraveljsonapi.php', 'laraveljsonapi');

        // Register the service the package provides.
        $this->app->singleton('laraveljsonapi', function ($app) {
            return new LaravelJsonApi;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['laraveljsonapi'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/laraveljsonapi.php' => config_path('laraveljsonapi.php'),
        ], 'laraveljsonapi.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/werk365'),
        ], 'laraveljsonapi.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/werk365'),
        ], 'laraveljsonapi.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/werk365'),
        ], 'laraveljsonapi.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
