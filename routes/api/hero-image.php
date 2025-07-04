<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HeroSection\HeroSectionController;


Route::middleware('auth:sanctum')->group(function () {
    // Hero Section Routes
    Route::prefix('hero-images')->group(function () {
        Route::post('/', [HeroSectionController::class, 'store']);
        Route::get('/', [HeroSectionController::class, 'index']);
        Route::delete('/{heroimage_id}', [HeroSectionController::class, 'destroy']);
    });
});
