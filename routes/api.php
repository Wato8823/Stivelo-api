<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Stive Clothing — v1
|--------------------------------------------------------------------------
| Toutes les routes définies ici sont automatiquement préfixées par
| /api/v1 (voir bootstrap/app.php : apiPrefix).
| Les réponses sont en JSON, les erreurs suivent un format unique
| (voir App\Exceptions\ApiExceptionRenderer).
*/

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
    'version' => '1.0.0',
    'time' => now()->toIso8601String(),
]))->name('api.health');
