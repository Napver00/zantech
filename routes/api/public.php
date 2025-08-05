<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Contact\ContactController;
use App\Http\Controllers\Ambassador\AmbassadorController;
use App\Http\Controllers\Company\CompanyController;
// Public Api

// Contact Us
Route::prefix('contact')->group(function () {
    Route::post('/', [ContactController::class, 'store']);
    Route::get('/', [ContactController::class, 'index']);
    Route::delete('/{contact_id}', [ContactController::class, 'destroy']);
});


// Ambassador Application
Route::prefix('ambassadors')->group(function () {
    Route::get('/', [AmbassadorController::class, 'index']);
    Route::post('/', [AmbassadorController::class, 'store']);
    Route::delete('/{id}', [AmbassadorController::class, 'destroy']);
});


// Company
Route::prefix('company')->group(function () {
    Route::get('/', [CompanyController::class, 'show']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::put('/{id}', [CompanyController::class, 'update']);
});
