<?php

use App\Exceptions\ApiErrorRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Toutes les routes /api/v1 : identifiant de requête, JSON forcé,
        // puis limitation de débit globale.
        $middleware->api(prepend: [
            AssignRequestId::class,
            ForceJsonResponse::class,
        ]);
        $middleware->throttleApi('public');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Derrière un répartiteur de charge / proxy TLS, on fait confiance aux
        // en-têtes X-Forwarded-* pour reconstruire l'URL et l'IP du client.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        ApiErrorRenderer::register($exceptions);
    })->create();
