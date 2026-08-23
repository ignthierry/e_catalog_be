<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\ShopeeImportController;

/*
|--------------------------------------------------------------------------
| API Routes — OMEGA TOYS E-Katalog
|--------------------------------------------------------------------------
*/

// Public & Customer API Endpoints
Route::prefix('')->group(function () {
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Banners & Settings & Media
    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/settings', [SettingController::class, 'index']);
    Route::get('/images/{filename}', [UploadController::class, 'serve']);
    Route::get('/ftp-diagnostic', [UploadController::class, 'diagnostics']);

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Customer In-App Orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/upload-proof', [OrderController::class, 'uploadProof']);

    // Real-Time Shipping Rate Calculations & Tracking (BinderByte)
    Route::get('/shipping/destinations', [ShippingController::class, 'searchDestinations']);
    Route::post('/shipping/calculate-cost', [ShippingController::class, 'calculateCost']);
    Route::get('/shipping/track', [ShippingController::class, 'trackAwb']);
    Route::get('/shipping/couriers', [ShippingController::class, 'listCouriers']);
});

// Admin Operations (Public or Sanctum Protected)
Route::prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Products
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Categories
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Banners
    Route::post('/banners', [BannerController::class, 'store']);
    Route::put('/banners/{id}', [BannerController::class, 'update']);
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

    // Settings & Uploads
    Route::post('/settings', [SettingController::class, 'update']);
    Route::post('/upload', [UploadController::class, 'upload']);

    // Orders Management
    Route::get('/orders', [OrderController::class, 'adminIndex']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [OrderController::class, 'adminUpdateStatus']);

    // Activity Logs / Audit Trail
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::delete('/activity-logs/clear', [ActivityLogController::class, 'clear']);

    // Shopee Store Synchronization & Data Reset
    Route::post('/shopee/reset-and-import', [ShopeeImportController::class, 'resetAndImport']);
    Route::post('/shopee/clear-data', [ShopeeImportController::class, 'clearData']);
});

// Member Account Settings & Profile Updates (Supports token in Authorization header)
Route::get('/auth/me', [AuthController::class, 'me']);
Route::match(['PUT', 'POST'], '/auth/profile', [AuthController::class, 'updateProfile']);
Route::match(['PUT', 'POST'], '/auth/password', [AuthController::class, 'updatePassword']);
Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Authenticated user session (Sanctum middleware alias)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::match(['PUT', 'POST'], '/profile', [AuthController::class, 'updateProfile']);
    Route::match(['PUT', 'POST'], '/password', [AuthController::class, 'updatePassword']);
    Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
});
