<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Contact\ContactController;
use App\Http\Controllers\Ambassador\AmbassadorController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Project\ProjectController;
// Public Api


Route::middleware('auth:sanctum')->group(function () {});


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
    Route::put('/{id}', [CompanyController::class, 'update']);
});

// project
Route::prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::put('/{id}', [ProjectController::class, 'update']);
    Route::delete('/{id}', [ProjectController::class, 'destroy']);

    Route::post('/technologie', [ProjectController::class, 'addTechnologies']);
    Route::delete('/technologie/{technologi_id}', [ProjectController::class, 'deleteTechnologies']);
});
