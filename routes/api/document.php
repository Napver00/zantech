<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Document\DocumentController;

Route::middleware('auth:sanctum')->group(function () {
    // Documentation Routes
    Route::prefix('documents')->group(function () {
        Route::get('/about', [DocumentController::class, 'showAbout']);
        Route::get('/term-condition', [DocumentController::class, 'showTrueCondition']);
        Route::get('/privacy-policy', [DocumentController::class, 'showPrivacyPolicy']);
        Route::get('/return-policy', [DocumentController::class, 'showReturnPolicy']);
        Route::put('/{document_id}', [DocumentController::class, 'update']);
        Route::get('/order-info', [DocumentController::class, 'showOrderInfo']);
        Route::put('/order-info/{orderinf_id}', [DocumentController::class, 'updateorderInfo']);
    });
});
