<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UserController;
use App\Models\User;

Route::middleware('auth:sanctum')->group(function () {

    // client routes
    Route::prefix('clints')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all-info/{user_id}', [UserController::class, 'shwoAllInfo']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/info', [UserController::class, 'loninUserInfo']);
        Route::get('/dashboard',[UserController::class, 'userDashboard']);
    });
});
