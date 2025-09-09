<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Post\PostController;


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('posts')->group(function () {
        Route::post('/', [PostController::class, 'store']);
        Route::put('/{post_id}', [PostController::class, 'update']);
        Route::patch('/status/{post_id}', [PostController::class, 'toggleStatus']);
        Route::delete('/{post_id}', [PostController::class, 'destroy']);
    });
});

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post_id}', [PostController::class, 'show']);
