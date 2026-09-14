<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DispatchController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PreparationController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Público
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('company', [CompanyController::class, 'show']);
        Route::put('company', [CompanyController::class, 'update']);

        Route::apiResource('users', UserController::class);
        Route::get('roles', [RoleController::class, 'index']);

        Route::apiResource('customers', CustomerController::class);

        Route::apiResource('products', ProductController::class);
        Route::get('products-lookup', [ProductController::class, 'lookup']);
        Route::get('product-categories', [ProductCategoryController::class, 'index']);

        Route::apiResource('vehicles', VehicleController::class)->except(['show']);

        Route::apiResource('orders', OrderController::class)->except(['destroy']);
        Route::post('orders/{order}/assign', [OrderController::class, 'assign']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('orders/{order}/trace', [OrderController::class, 'trace']);

        Route::get('orders/{order}/preparation', [PreparationController::class, 'show']);
        Route::post('orders/{order}/preparation/items', [PreparationController::class, 'storeItem']);
        Route::post('orders/{order}/preparation/scan', [PreparationController::class, 'scan']);
        Route::post('orders/{order}/preparation/finish', [PreparationController::class, 'finish']);

        Route::get('reviews/pending', [ReviewController::class, 'pending']);
        Route::post('orders/{order}/review/approve', [ReviewController::class, 'approve']);
        Route::post('orders/{order}/review/reject', [ReviewController::class, 'reject']);

        Route::get('dispatches', [DispatchController::class, 'index']);
        Route::get('dispatches/{dispatch}', [DispatchController::class, 'show']);
        Route::post('orders/{order}/dispatch', [DispatchController::class, 'store']);

        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('dashboard/charts', [DashboardController::class, 'charts']);

        Route::get('reports/orders', [ReportController::class, 'orders']);
        Route::get('reports/products', [ReportController::class, 'products']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });
});
