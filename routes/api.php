<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Tag\TagController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\Supplier\SupplierController;
use App\Http\Controllers\Challan\ChallanController;
use App\Http\Controllers\Rating\RatingController;
use App\Http\Controllers\Expense\ExpenseController;

// Admin Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// User Auth
Route::prefix('users')->group(function () {
    Route::post('/register', [AuthController::class, 'Userregister']);
    Route::post('/login', [AuthController::class, 'Userlogin']);
});


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

    // All Suppliers Routes
    Route::prefix('suppliers')->group(function () {
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/{suppliers_id}', [SupplierController::class, 'show']);
        Route::put('/{suppliers_id}', [SupplierController::class, 'update']);
        Route::delete('/{suppliers_id}', [SupplierController::class, 'delete']);
    });

    // All Challan Routes
    Route::prefix('challans')->group(function () {
        Route::post('/', [ChallanController::class, 'store']);
        Route::get('/', [ChallanController::class, 'index']);
        Route::get('/{challans_id}', [ChallanController::class, 'show']);
    });

    // Rating Routes
    Route::prefix('ratings')->group(function () {
        Route::post('/', [RatingController::class, 'store']);
        Route::get('/', [RatingController::class, 'index']);
        Route::post('/toggle-status/{Rating_id}', [RatingController::class, 'toggleStatus']);
    });

    // Expense Routes
    Route::prefix('expenses')->group(function () {
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/', [ExpenseController::class, 'index']);
        Route::put('/{expense_id}', [ExpenseController::class, 'update']);
    });
});
