<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Stuff\StuffController;


Route::middleware('auth:sanctum')->group(function () {

    // client routes
    Route::prefix('stuff')->group(function () {
        Route::get('/', [StuffController::class, 'index']);
        // Route::get('/all-info/{user_id}', [StuffController::class, 'shwoAllInfo']);
    });
});
