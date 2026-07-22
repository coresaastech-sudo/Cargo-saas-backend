<?php

namespace Modules\Ap\Providers;

use Illuminate\Support\ServiceProvider;

class ApServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Ap';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
