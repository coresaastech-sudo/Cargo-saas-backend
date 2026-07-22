<?php

namespace Modules\Re\Providers;

use Illuminate\Support\ServiceProvider;

class ReServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Re';

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
