<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Tag\TagController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\BundleItem\BundleItemController;

Route::middleware('auth:sanctum')->group(function () {
    // All Products Routes
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/category/{category_id}', [ProductController::class, 'shwoProductCategory']);
        Route::get('/{product_id}', [ProductController::class, 'show']);
        Route::post('/toggle-status/{product_id}', [ProductController::class, 'toggleStatus']);
        Route::put('/update/{product_id}', [ProductController::class, 'updateProduct']);
        Route::delete('/delete/{product_id}', [ProductController::class, 'deleteProduct']);
        Route::post('/add-categories/{product_id}', [CategoryController::class, 'addCategories']);
        Route::delete('/remove-categories/{product_id}', [CategoryController::class, 'removeCategories']);
        Route::post('/add-tags/{product_id}', [TagController::class, 'addTags']);
        Route::delete('/remove-tags/{product_id}', [TagController::class, 'removeTags']);
        Route::post('/add-images/{product_id}', [FileController::class, 'addImagesProduct']);
        Route::get('/files/image', [FileController::class, 'getProductFiles']);
        Route::delete('/remove-image/{product_id}', [FileController::class, 'removePeoductImage']);
        Route::prefix('bundles')->group(function () {
            Route::post('/{bundleId}', [BundleItemController::class, 'addItemsToBundle']);
            Route::post('/update-quantity/{bundleId}', [BundleItemController::class, 'updateQuantity']);
            Route::post('/toggle-bundle/{product_id}', [BundleItemController::class, 'toggleBundle']);
            Route::delete('/delete/{bundleId}', [BundleItemController::class, 'deleteBundle']);
        });
    });
    Route::get('/in-stock-products', [ProductController::class, 'inStockProducts']);
    Route::get('/buying-price-items', [ProductController::class, 'getitemsByBuyingPrice']);
    Route::get('/item-buying-history/{item_id}', [ProductController::class, 'getItemBuyingHistory']);
});
