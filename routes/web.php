<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'description' => 'API REST de la boutique Stive Clothing — catalogue, personnalisation sur mesure et demandes WhatsApp.',
        'version' => '1.0.0',
        'documentation' => url('/docs/openapi.yaml'),
        'endpoints' => [
            'health' => url('/api/v1/health'),
            'categories' => url('/api/v1/categories'),
            'products' => url('/api/v1/products'),
            'orders' => url('/api/v1/orders'),
            'admin_login' => url('/api/v1/admin/login'),
        ],
    ]);
});

Route::get('/docs/openapi.yaml', function () {
    $path = base_path('docs/openapi.yaml');

    abort_unless(file_exists($path), 404);

    return response()->file($path, ['Content-Type' => 'application/yaml; charset=UTF-8']);
});
