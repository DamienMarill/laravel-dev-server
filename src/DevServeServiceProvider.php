<?php

namespace Marill\DevServe;

use Marill\DevServe\Commands\DevLogsCommand;
use Marill\DevServe\Commands\DevServeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DevServeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-dev-serve')
            ->hasConfigFile()  // Garde ceci pour la configuration
            ->hasCommand(DevServeCommand::class)
            ->hasCommand(DevLogsCommand::class)
            ->publishesServiceProvider();
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // Publication de la configuration
        $this->publishes([
            __DIR__.'/../config/dev-serve.php' => config_path('dev-serve.php'),
        ], 'dev-serve-config');

        // Enregistrement des commandes
        if ($this->app->runningInConsole()) {
            $this->commands([
                DevServeCommand::class,
                DevLogsCommand::class,
            ]);
        }

        // Merge
        $this->mergeConfigFrom(
            __DIR__.'/../config/dev-serve.php', 'dev-serve'
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Fusion de la configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/dev-serve.php', 'dev-serve'
        );

        // Binding du service
        $this->app->singleton('dev-serve', function ($app) {
            return new DevServe($app);
        });
    }
}
