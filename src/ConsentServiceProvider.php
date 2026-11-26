<?php

declare(strict_types=1);

namespace SimoneCerruti\Consent;

use Illuminate\Support\ServiceProvider;
use SimoneCerruti\Consent\Console\Commands\ConsentCommand;

final class ConsentServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        $this->mergeConfigFrom(__DIR__.'/../config/consent.php', 'consent');

        $this->app->singleton(Consent::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        $this->loadRoutesFrom(__DIR__.'/../routes/consent.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'consent');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'consent');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/consent.php' => config_path('consent.php'),
        ], ['consent', 'consent-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/consent'),
        ], ['consent', 'consent-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/consent'),
        ], ['consent', 'consent-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/consent'),
        ], ['consent', 'consent-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['consent', 'consent-migrations']);

        $this->commands([
            ConsentCommand::class,
        ]);
    }
}
