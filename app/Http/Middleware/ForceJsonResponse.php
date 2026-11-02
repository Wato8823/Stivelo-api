<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'API ne produit que du JSON : on force l'en-tête Accept pour que les
 * erreurs de validation et d'authentification soient rendues en JSON même
 * si le client (Angular, Postman) omet l'en-tête.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
