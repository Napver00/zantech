<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Contact\ContactController;

// Public Api

// Contact Us
Route::prefix('contact')->group(function () {
    Route::post('/', [ContactController::class, 'store']);
    Route::get('/', [ContactController::class, 'index']);
    Route::delete('/{contact_id}', [ContactController::class, 'destroy']);
});
