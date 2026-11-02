<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * POST /api/v1/orders — enregistre la demande et renvoie le lien WhatsApp.
     *
     * Limitation de débit : `throttle:orders` (5 demandes par minute et par IP).
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $result = $this->orders->createFromPayload($request->validated(), $request);

        return response()->json([
            'data' => (new OrderResource($result['order']))
                ->withWhatsAppUrl($result['whatsapp_url'])
                ->resolve($request),
            'message' => 'Votre demande est enregistrée. Vérifiez le message WhatsApp puis envoyez-le pour finaliser.',
        ], 201);
    }
}
