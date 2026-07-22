<?php

namespace Modules\Gl\Providers;

use Illuminate\Support\ServiceProvider;

class GlServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Gl';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
