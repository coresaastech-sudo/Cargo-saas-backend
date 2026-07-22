<?php

namespace Modules\Ca\Providers;

use Illuminate\Support\ServiceProvider;

class CaServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Ca';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
