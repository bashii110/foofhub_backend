<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController, CategoryController, ProductController, OrderController, PaymentController};
use App\Http\Controllers\Admin\{DashboardController, OrderController as AdminOrderController, UserController as AdminUserController};

// NO Route::prefix('api') here - bootstrap/app.php adds it automatically

// ── PUBLIC ROUTES ──────────────────────────────────────────
Route::post  ('auth/register',        [AuthController::class, 'register']);
Route::post  ('auth/login',           [AuthController::class, 'login']);
Route::post  ('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post  ('auth/reset-password',  [AuthController::class, 'resetPassword']);

Route::get   ('categories',           [CategoryController::class, 'index']);
Route::get   ('products',             [ProductController::class,  'index']);
Route::get   ('products/{product}',   [ProductController::class,  'show']);

// ── AUTHENTICATED ROUTES ───────────────────────────────────
Route::middleware('auth:api')->group(function () {
    Route::post  ('auth/logout',       [AuthController::class, 'logout']);
    Route::get   ('auth/me',           [AuthController::class, 'me']);
    Route::post  ('auth/refresh',      [AuthController::class, 'refresh']);
    Route::put   ('auth/profile',      [AuthController::class, 'updateProfile']);

    Route::post  ('orders',            [OrderController::class, 'store']);
    Route::get   ('orders',            [OrderController::class, 'index']);
    Route::get   ('orders/{order}',    [OrderController::class, 'show']);
    Route::post  ('orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::post  ('orders/{order}/payment/proof', [PaymentController::class, 'uploadProof']);

    Route::post  ('products',                     [ProductController::class,  'store']);
    Route::put   ('products/{product}',           [ProductController::class,  'update']);
    Route::delete('products/{product}',           [ProductController::class,  'destroy']);
    Route::post  ('categories',                   [CategoryController::class, 'store']);
    Route::put   ('categories/{category}',        [CategoryController::class, 'update']);
    Route::delete('categories/{category}',        [CategoryController::class, 'destroy']);

    Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
         Route::get('dashboard', [DashboardController::class, 'stats']);
         Route::get('orders', [AdminOrderController::class, 'index']);
         Route::get('orders/{order}', [AdminOrderController::class, 'show']);
         Route::post('orders/{order}/verify', [AdminOrderController::class, 'verifyPayment']);
         Route::post('orders/{order}/reject', [AdminOrderController::class, 'rejectPayment']);
         Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
         Route::get('users', [AdminUserController::class, 'index']);
         Route::get('users/{user}', [AdminUserController::class, 'show']);
         Route::post('users/{user}/toggle-block', [AdminUserController::class, 'toggleBlock']);
    });

});