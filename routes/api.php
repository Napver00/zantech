<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Tag\TagController;
use App\Http\Controllers\File\FileController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // All Categories Routes
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
        Route::patch('/toggle-status/{id}', [CategoryController::class, 'toggleStatus']);
    });

    // All Products Routes
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{product_id}', [ProductController::class, 'show']);
        Route::post('/toggle-status/{product_id}', [ProductController::class, 'toggleStatus']);
        Route::put('/update/{product_id}', [ProductController::class, 'updateProduct']);
        Route::delete('/delete/{product_id}', [ProductController::class, 'deleteProduct']);
        Route::post('/add-categories/{product_id}', [CategoryController::class, 'addCategories']);
        Route::delete('/remove-categories/{product_id}', [CategoryController::class, 'removeCategories']);
        Route::post('/add-tags/{product_id}', [TagController::class, 'addTags']);
        Route::delete('/remove-tags/{product_id}', [TagController::class, 'removeTags']);
        Route::post('/add-images/{product_id}', [FileController::class, 'addImagesProduct']);
        Route::delete('/remove-image/{product_id}', [FileController::class, 'removePeoductImage']);
    });
});
