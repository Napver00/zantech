<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\AdminDashboardController;


Route::middleware('auth:sanctum')->group(function () {
    // Admin dashboard routes
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'adminDashboard']);
});
