<?php

namespace Modules\Cr\Providers;

use Illuminate\Support\ServiceProvider;

class CrServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Cr';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
