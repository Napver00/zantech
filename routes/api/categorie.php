<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;

Route::middleware('auth:sanctum', 'role:admin,stuff,member')->group(function () {

    // All Categories Routes
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{categorie_id}', [CategoryController::class, 'show']);
        Route::put('/{categorie_id}', [CategoryController::class, 'update']);
        Route::delete('/{categorie_id}', [CategoryController::class, 'destroy']);
        Route::patch('/toggle-status/{categorie_id}', [CategoryController::class, 'toggleStatus']);
    });
});
