<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    /**
     * GET /api/v1/shop — configuration publique consommée par Angular :
     * numéro WhatsApp, devise, tailles, bornes de mesures et statuts.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'name' => 'Stive Clothing',
                'tagline' => 'L’excellence sur mesure',
                'whatsapp' => [
                    'number' => WhatsAppLinkBuilder::number(),
                    'default_message' => WhatsAppLinkBuilder::defaultMessage(),
                    'chat_url' => WhatsAppLinkBuilder::link(),
                ],
                'currency' => [
                    'code' => config('catalog.currency.code'),
                    'label' => config('catalog.currency.label'),
                    'decimals' => config('catalog.currency.decimals'),
                ],
                'sizes' => config('catalog.sizes'),
                'custom_size_label' => 'Sur mesure',
                'measurements' => [
                    'unit' => config('catalog.measurements.unit'),
                    'min' => config('catalog.measurements.min'),
                    'max' => config('catalog.measurements.max'),
                ],
                'order_statuses' => OrderStatus::options(),
                'measurement_retention_days' => config('catalog.orders.measurement_retention_days'),
                'endpoints' => [
                    'categories' => route('api.categories.index'),
                    'products' => route('api.products.index'),
                    'orders' => route('api.orders.store'),
                ],
            ],
        ]);
    }
}
