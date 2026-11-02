<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
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
| Administration — authentification
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('api.admin.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});
