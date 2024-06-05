<?php

namespace Api\LaravelAutocrud;

use Illuminate\Support\ServiceProvider;

class AutoCrudServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\AutoCrudCreate::class,
            ]);
            $this->publishes([
                __DIR__ . '\Commands\stubs' => resource_path('stubs'),
            ], 'api-autocrud');
        }
    }
}
