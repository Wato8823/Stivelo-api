<?php

/*
|--------------------------------------------------------------------------
| Configuration métier — Stive Clothing
|--------------------------------------------------------------------------
| Valeurs de référence du cahier des charges : devise FCFA (XAF), canal de
| commande WhatsApp, statuts de demande, bornes de mesures en centimètres.
| Aucune de ces valeurs ne doit être codée en dur dans le code applicatif.
*/

return [

    // Numéro WhatsApp professionnel, format international, sans "+" ni espaces.
    'whatsapp' => [
        'number' => env('STIVE_WHATSAPP_NUMBER', '237600000000'),
        'default_message' => env(
            'STIVE_WHATSAPP_DEFAULT_MESSAGE',
            'Bonjour Stive Clothing, je souhaite des informations sur une pièce.'
        ),
        'base_url' => 'https://wa.me/',
    ],

    // Devise de référence : franc CFA (XAF), montants stockés en entiers.
    'currency' => [
        'code' => env('STIVE_CURRENCY', 'XAF'),
        'label' => 'FCFA',
        'decimals' => 0,
    ],

    // Préfixe des références publiques de commande : STV-2026-00A31.
    'orders' => [
        'prefix' => env('STIVE_ORDER_PREFIX', 'STV'),
        'channel' => 'whatsapp',
        'statuses' => [
            'pending_confirmation' => 'En attente de confirmation',
            'confirmed' => 'Confirmée',
            'in_production' => 'En confection',
            'ready' => 'Prête',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
        ],
        // Cycle de vie autorisé : une demande ne peut pas revenir en arrière.
        'transitions' => [
            'pending_confirmation' => ['confirmed', 'cancelled'],
            'confirmed' => ['in_production', 'cancelled'],
            'in_production' => ['ready', 'cancelled'],
            'ready' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ],
        'default_status' => 'pending_confirmation',
        // Durée indicative de conservation des mesures, en jours.
        'measurement_retention_days' => 365,
    ],

    // Bornes de validation des mensurations corporelles (centimètres).
    'measurements' => [
        'unit' => 'cm',
        'min' => 20,
        'max' => 250,
    ],

    // Tailles standard proposées par défaut aux nouveaux modèles.
    'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],

    // Limitation de débit des endpoints publics et d'authentification.
    'rate_limits' => [
        'orders_per_minute' => (int) env('STIVE_ORDERS_PER_MINUTE', 5),
        'login_per_minute' => (int) env('STIVE_LOGIN_ATTEMPTS_PER_MINUTE', 5),
        'public_per_minute' => 120,
    ],

    // Médias produits.
    'media' => [
        'disk' => env('PRODUCT_MEDIA_DISK', 'public'),
        'max_kb' => (int) env('PRODUCT_IMAGE_MAX_KB', 4096),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
    ],

];
