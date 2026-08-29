<?php

use App\Http\Controllers\Admin\PaymentSettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\{
    CategoryController,
    ProductController,
    OrderController,
    PaymentController
};
use App\Http\Controllers\Admin\{
    DashboardController,
    OrderController  as AdminOrderController,
    UserController   as AdminUserController
};

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/...
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PUBLIC AUTH ROUTES
    |--------------------------------------------------------------------------
    */
    Route::middleware('throttle:auth')->prefix('auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('login',           [AuthController::class, 'login']);

        // Password Reset Routes
        Route::post('forgot-password',    [AuthController::class, 'forgotPassword']);
        Route::post('verify-reset-otp',   [AuthController::class, 'verifyResetOtp']);
        Route::post('reset-password',     [AuthController::class, 'resetPassword']);
    });

    /*
    |--------------------------------------------------------------------------
    | PUBLIC CATALOGUE ROUTES
    |--------------------------------------------------------------------------
    */
    Route::get('categories',           [CategoryController::class, 'index']);
    Route::get('products',             [ProductController::class, 'index']);
    Route::get('products/{product}',   [ProductController::class, 'show']);

    // Public — Flutter fetches active payment accounts on checkout screen
    Route::get('payment-settings',     [PaymentSettingsController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED ROUTES
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {

        // Auth actions
        Route::prefix('auth')->group(function () {
            Route::post('logout',    [AuthController::class, 'logout']);
            Route::get('me',         [AuthController::class, 'me']);
            Route::post('refresh',   [AuthController::class, 'refresh']);
            Route::put('profile',    [AuthController::class, 'updateProfile']);
            Route::post('fcm-token', [AuthController::class, 'updateFcmToken']);
        });

        // Customer orders
        Route::prefix('orders')->group(function () {
            Route::get('',                               [OrderController::class, 'index']);
            Route::post('',                              [OrderController::class, 'store']);
            Route::get('{order}',                        [OrderController::class, 'show']);
            Route::post('{order}/cancel',                [OrderController::class, 'cancel']);
            Route::post('{order}/payment/proof',         [PaymentController::class, 'uploadProof']);
        });

        /*
        |--------------------------------------------------------------------------
        | STAFF & ADMIN: Catalogue Management
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:admin,staff')->group(function () {

            // Products management
            Route::post('products',                    [ProductController::class, 'store']);
            Route::put('products/{product}',           [ProductController::class, 'update']);
            Route::delete('products/{product}',        [ProductController::class, 'destroy']);
            Route::patch('products/{product}/toggle',  [ProductController::class, 'toggleAvailability']);
            Route::post('products/{product}/update',   [ProductController::class, 'updateWithImage']);

            // Categories management
            Route::post('categories',                  [CategoryController::class, 'store']);
            Route::put('categories/{category}',        [CategoryController::class, 'update']);
            Route::delete('categories/{category}',     [CategoryController::class, 'destroy']);
        });

        /*
        |--------------------------------------------------------------------------
        | ADMIN PANEL
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:admin,staff')->prefix('admin')->group(function () {

            // Dashboard
            Route::get('dashboard',          [DashboardController::class, 'stats']);
            Route::get('dashboard/revenue',  [DashboardController::class, 'revenueReport']);

            // Orders management
            Route::prefix('orders')->group(function () {
                Route::get('',                 [AdminOrderController::class, 'index']);
                Route::get('{order}',          [AdminOrderController::class, 'show']);
                Route::post('{order}/verify',  [AdminOrderController::class, 'verifyPayment']);
                Route::post('{order}/reject',  [AdminOrderController::class, 'rejectPayment']);
                Route::put('{order}/status',   [AdminOrderController::class, 'updateStatus']);
                Route::post('{order}/note',    [AdminOrderController::class, 'addNote']);
            });

            // Users management
            Route::prefix('users')->group(function () {
                Route::get('',       [AdminUserController::class, 'index']);
                Route::get('{user}', [AdminUserController::class, 'show']);

                Route::middleware('role:admin')->group(function () {
                    Route::post('{user}/toggle-block', [AdminUserController::class, 'toggleBlock']);
                    Route::put('{user}/role',          [AdminUserController::class, 'updateRole']);
                    Route::delete('{user}',            [AdminUserController::class, 'destroy']);
                });
            });

            // Payment settings — admin can view all and update account details
            Route::prefix('payment-settings')->group(function () {
                Route::get('',                         [PaymentSettingsController::class, 'adminIndex']);
                Route::put('{paymentSetting}',         [PaymentSettingsController::class, 'update']);
            });
        });
    });
});

// Health check
Route::get('/health', fn () => response()->json([
    'status'    => 'ok',
    'timestamp' => now()->toISOString(),
    'version'   => config('app.version', '1.0.0'),
]));