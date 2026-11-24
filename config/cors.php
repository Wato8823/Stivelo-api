<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    | Le frontend (Cloudflare Pages) est sur une autre origine que l'API.
    | Seuls les domaines listés dans CORS_ALLOWED_ORIGINS (séparés par des
    | virgules) peuvent appeler l'API depuis un navigateur.
    |
    | L'authentification de l'administration repose sur un jeton Bearer
    | (Sanctum), pas sur les cookies : supports_credentials reste false par
    | défaut. Passer CORS_SUPPORTS_CREDENTIALS=true seulement si le frontend
    | bascule sur le mode "cookie SPA".
    */

    'paths' => ['api/*', 'docs/openapi.yaml'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        fn (string $origin) => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 3600,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
