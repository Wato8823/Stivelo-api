<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès par rôle : `role:admin` ou `role:admin,advisor`.
 * Le contrôle fin (par ressource) est assuré par les policies.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Authentification requise.');

        if (! $user->isActive()) {
            abort(403, 'Ce compte est désactivé.');
        }

        $allowed = array_map(
            fn (string $role) => UserRole::tryFrom($role) ?? abort(500, "Rôle inconnu : {$role}"),
            $roles,
        );

        abort_unless($user->hasAnyRole(...$allowed), 403, 'Vous n’avez pas les droits nécessaires pour cette action.');

        return $next($request);
    }
}
