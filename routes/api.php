<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductMediaController;
use App\Http\Controllers\Api\V1\Admin\StatsController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Stive Clothing — v1
|--------------------------------------------------------------------------
| Les routes définies ici sont automatiquement préfixées par /api/v1
| (bootstrap/app.php : apiPrefix). Les réponses sont en JSON et les
| erreurs suivent un format unique (App\Exceptions\ApiErrorRenderer).
*/

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
    'version' => '1.0.0',
    'currency' => config('catalog.currency.code'),
    'time' => now()->toIso8601String(),
]))->name('api.health');

/*
|--------------------------------------------------------------------------
| Boutique — public
|--------------------------------------------------------------------------
*/
Route::get('/shop', [ShopController::class, 'show'])->name('api.shop.show');
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('api.products.show');

Route::post('/orders', [OrderController::class, 'store'])
    ->middleware('throttle:orders')
    ->name('api.orders.store');

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('api.admin.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');

        // Demandes : conseillers et administrateurs (atelier en lecture seule).
        Route::middleware('role:admin,advisor,atelier')->group(function () {
            Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        });

        Route::patch('/orders/{order}', [AdminOrderController::class, 'updateStatus'])
            ->middleware('role:admin,advisor')
            ->name('orders.update-status');

        // Catalogue : consultation pour l'équipe, écriture réservée à l'admin.
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');

        Route::middleware('role:admin')->group(function () {
            Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
            Route::match(['put', 'patch'], '/products/{product}', [AdminProductController::class, 'update'])
                ->name('products.update');
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])
                ->name('products.destroy');

            Route::post('/products/{product}/media', [ProductMediaController::class, 'store'])
                ->name('products.media.store');
            Route::delete('/products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
                ->name('products.media.destroy');

            Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
            Route::match(['put', 'patch'], '/categories/{category}', [AdminCategoryController::class, 'update'])
                ->name('categories.update');
            Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])
                ->name('categories.destroy');
        });
    });
});
