<?php

namespace Modules\Ad\Providers;

use Illuminate\Support\ServiceProvider;

class AdServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Ad';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
