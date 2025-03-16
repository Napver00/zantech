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
use App\Http\Controllers\BundleItem\BundleItemController;
use App\Http\Controllers\HeroSection\HeroSectionController;
use App\Http\Controllers\Document\DocumentController;
use App\Http\Controllers\Shipping\ShippingController;
use App\Http\Controllers\Coupon\CouponController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Transition\TransitionController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Wishlist\WishlistController;
use App\Http\Controllers\Email\EmailController;
use App\Http\Controllers\Passwort\ForgotPasswordController;
use App\Http\Controllers\Dashboard\AdminDashboardController;

// Admin Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// User Auth
Route::prefix('users')->group(function () {
    Route::post('/register', [AuthController::class, 'Userregister']);
    Route::post('/login', [AuthController::class, 'Userlogin']);
});
// Email varifications
Route::get('/email/verify/{id}/{hash}', [EmailController::class, 'verify'])
    ->name('verification.verify');

Route::post('/email/resend-verification', [EmailController::class, 'resendVerificationEmail'])
    ->name('verification.resend');

// Forgot Password
Route::post('/password/forgot', [ForgotPasswordController::class, 'forgotPassword'])
    ->name('password.forgot');

// Reset Password
Route::post('/password/reset', [ForgotPasswordController::class, 'resetPassword'])
    ->name('password.reset');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/update-user-info', [AuthController::class, 'updateUserInfo']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // All Categories Routes
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{categorie_id}', [CategoryController::class, 'show']);
        Route::put('/{categorie_id}', [CategoryController::class, 'update']);
        Route::delete('/{categorie_id}', [CategoryController::class, 'destroy']);
        Route::patch('/toggle-status/{categorie_id}', [CategoryController::class, 'toggleStatus']);
    });

    // All Products Routes
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{category_id}', [ProductController::class, 'shwoProductCategory']);
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
        Route::post('/toggle-status/{rating_id}', [RatingController::class, 'toggleStatus']);
    });

    // Expense Routes
    Route::prefix('expenses')->group(function () {
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/', [ExpenseController::class, 'index']);
        Route::put('/{expense_id}', [ExpenseController::class, 'update']);
    });

    // Hero Section Routes
    Route::prefix('hero-images')->group(function () {
        Route::post('/', [HeroSectionController::class, 'store']);
        Route::get('/', [HeroSectionController::class, 'index']);
        Route::delete('/{heroimage_id}', [HeroSectionController::class, 'destroy']);
    });

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

    // Shipping Address Routes
    Route::prefix('shipping-addresses')->group(function () {
        Route::post('/', [ShippingController::class, 'store']);
        Route::get('/', [ShippingController::class, 'index']);
        Route::get('/{shipping_id}', [ShippingController::class, 'show']);
        Route::put('/{shipping_id}', [ShippingController::class, 'update']);
        Route::delete('/{shipping_id}', [ShippingController::class, 'destroy']);
    });

    // Coupon Routes
    Route::prefix('coupons')->group(function () {
        Route::post('/', [CouponController::class, 'store']);
        Route::get('/', [CouponController::class, 'index']);
        Route::put('/{coupon_id}', [CouponController::class, 'update']);
        Route::delete('/{coupon_id}', [CouponController::class, 'destroy']);
    });

    // Order Routes for user
    Route::prefix('orders')->group(function () {
        Route::post('/place-order', [OrderController::class, 'placeOrder']);
        Route::get('/', [OrderController::class, 'userindex']);
        Route::get('/{order_Id}', [OrderController::class, 'show']);
        Route::post('/sendOrderEmails/{order_Id}', [OrderController::class, 'sendOrderEmails']);
    });

    // Order Routes for admin
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'adminindex']);
        Route::put('/update-status/{order_Id}', [OrderController::class, 'updateStatus']);
        Route::post('/add-product/{orderId}', [OrderController::class, 'addProductToOrder']);
        Route::delete('/products/{order_Id}/remove/{product_Id}', [OrderController::class, 'removeProductFromOrder']);
        Route::put('/products/{order_Id}/update-quantity/{product_Id}', [OrderController::class, 'updateProductQuantity']);
    });

    // payment routes
    Route::prefix('paymens')->group(function () {
        Route::put('/update-status/{paymentId}', [PaymentController::class, 'updatePaymentStatus']);
        Route::put('/update-padi-amount/{paymentId}', [PaymentController::class, 'updatePadiAmount']);
    });

    // Transaction routes
    Route::prefix('transiions')->group(function () {
        Route::get('/', [TransitionController::class, 'index']);
    });

    // client routes
    Route::prefix('clints')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all-info', [UserController::class, 'shwoAllInfo']);
    });

    // whshltist routes
    Route::prefix('wishlist')->group(function () {
        Route::post('/', [WishlistController::class, 'store']);
        Route::get('/{user_id}', [WishlistController::class, 'show']);
        Route::delete('/{wishlist_id}', [WishlistController::class, 'destroy']);
    });

    // Activity routes
    Route::prefix('activitys')->group(function () {
        Route::get('/', [UserController::class, 'getActivities']);
    });

    // Admin dashboard routes
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'adminDashboard']);
});
