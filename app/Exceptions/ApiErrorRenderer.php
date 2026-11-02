<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Format d'erreur unique de l'API :
 *
 *   {
 *     "message": "Les données envoyées sont invalides.",
 *     "code": "validation_failed",
 *     "errors": { "variant.size": ["La taille choisie n'est pas disponible."] },
 *     "request_id": "0f6f…"
 *   }
 *
 * Aucune trace interne, requête SQL ou donnée personnelle n'est exposée.
 */
class ApiErrorRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            return self::isApi($request) ? self::make(
                $request,
                'validation_failed',
                'Les données envoyées sont invalides.',
                422,
                $exception->errors(),
            ) : null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return self::isApi($request) ? self::make(
                $request,
                'unauthenticated',
                'Authentification requise.',
                401,
            ) : null;
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return self::isApi($request) ? self::make(
                $request,
                'forbidden',
                $exception->getMessage() ?: 'Vous n’avez pas les droits nécessaires pour cette action.',
                403,
            ) : null;
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            return self::isApi($request) ? self::make(
                $request,
                'not_found',
                'La ressource demandée est introuvable.',
                404,
            ) : null;
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            return self::isApi($request) ? self::make(
                $request,
                'not_found',
                'La ressource demandée est introuvable.',
                404,
            ) : null;
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! self::isApi($request)) {
                return null;
            }

            $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 60);

            return self::make(
                $request,
                'too_many_requests',
                'Trop de requêtes. Merci de patienter quelques instants.',
                429,
            )->header('Retry-After', (string) $retryAfter);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! self::isApi($request)) {
                return null;
            }

            $status = $exception->getStatusCode();

            return self::make(
                $request,
                self::codeForStatus($status),
                $exception->getMessage() ?: self::messageForStatus($status),
                $status,
            )->withHeaders($exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! self::isApi($request)) {
                return null;
            }

            // HttpResponseException transporte déjà une réponse complète
            // (par exemple le message personnalisé d'un limiteur de débit) :
            // le framework doit la renvoyer telle quelle, sans passer par le
            // rendu générique ci-dessous.
            if ($exception instanceof HttpResponseException) {
                return null;
            }

            report($exception);

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Une erreur interne est survenue. Merci de réessayer ou de contacter la boutique.';

            return self::make($request, 'server_error', $message, 500);
        });
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function make(Request $request, string $code, string $message, int $status, array $errors = [])
    {
        $payload = [
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        $payload['request_id'] = $request->attributes->get('request_id');

        return response()->json($payload, $status);
    }

    private static function isApi(Request $request): bool
    {
        return $request->is('api/*');
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            413 => 'payload_too_large',
            422 => 'validation_failed',
            429 => 'too_many_requests',
            default => 'http_error',
        };
    }

    private static function messageForStatus(int $status): string
    {
        return match ($status) {
            405 => 'Cette méthode HTTP n’est pas autorisée sur cette ressource.',
            409 => 'La requête est en conflit avec l’état actuel de la ressource.',
            413 => 'Le contenu envoyé est trop volumineux.',
            default => 'La requête n’a pas pu être traitée.',
        };
    }
}
