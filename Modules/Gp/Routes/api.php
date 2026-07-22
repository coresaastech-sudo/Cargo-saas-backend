<?php

use Illuminate\Support\Facades\Route;
use Modules\Gp\Http\Controllers\ActionGatewayController;

Route::prefix('v1')->group(function () {
    Route::post('back/action', [ActionGatewayController::class, 'dispatch'])->name('api.v1.back.action');
});
