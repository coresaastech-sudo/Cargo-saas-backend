<?php

namespace Modules\Gp\Providers;

use Illuminate\Support\ServiceProvider;

class GpServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Gp';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
