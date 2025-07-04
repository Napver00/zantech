<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rating\RatingController;

Route::middleware('auth:sanctum')->group(function () {

    // Rating Routes
    Route::prefix('ratings')->group(function () {
        Route::post('/', [RatingController::class, 'store']);
        Route::get('/', [RatingController::class, 'index']);
        Route::post('/toggle-status/{rating_id}', [RatingController::class, 'toggleStatus']);
    });
});
