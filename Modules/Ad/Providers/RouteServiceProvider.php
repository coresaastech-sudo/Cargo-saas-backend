<?php

namespace Modules\Ad\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(base_path('Modules/Ad/Routes/api.php'));
    }
}
