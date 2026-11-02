<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\OrderDetailResource;
use App\Http\Resources\Admin\OrderListResource;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Support\OrderMessageBuilder;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * GET /api/v1/admin/orders — suivi des demandes, filtrable.
     */
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::query()
            ->with(['items'])
            ->withStatus($request->filled('status') ? OrderStatus::from((string) $request->input('status')) : null)
            ->search($request->input('q'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at');

        $orders = $query->paginate(
            (int) $request->integer('per_page', 20),
            ['*'],
            'page',
            max(1, (int) $request->integer('page', 1)),
        )->withQueryString();

        $counts = Order::query()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return response()->json([
            'data' => OrderListResource::collection($orders->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'counts_by_status' => $counts,
            ],
            'links' => [
                'prev' => $orders->previousPageUrl(),
                'next' => $orders->nextPageUrl(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/orders/{order} — dossier complet.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['items.measurements', 'events.actor']);

        return response()->json([
            'data' => (new OrderDetailResource($order))
                ->withMeasurements($request->user()->can('viewMeasurements', $order))
                ->resolve($request),
            'whatsapp_url' => WhatsAppLinkBuilder::linkForText(OrderMessageBuilder::forOrder($order)),
        ]);
    }

    /**
     * PATCH /api/v1/admin/orders/{order} — changement d'état contrôlé.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $target = $request->targetStatus();
        $current = $order->status;

        if ($current === $target) {
            return response()->json([
                'message' => 'La demande est déjà dans cet état.',
                'code' => 'status_unchanged',
                'request_id' => $request->attributes->get('request_id'),
            ], 409);
        }

        if (! $current->canTransitionTo($target)) {
            return response()->json([
                'message' => sprintf(
                    'Transition impossible de « %s » vers « %s ». États autorisés : %s.',
                    $current->label(),
                    $target->label(),
                    collect($current->allowedTransitions())->map(fn ($status) => $status->label())->implode(', ') ?: 'aucun',
                ),
                'code' => 'invalid_status_transition',
                'request_id' => $request->attributes->get('request_id'),
            ], 409);
        }

        DB::transaction(function () use ($order, $current, $target, $request) {
            $order->status = $target;

            if ($target === OrderStatus::Confirmed) {
                $order->confirmed_at = now();
            }

            if ($target === OrderStatus::Cancelled) {
                $order->cancelled_at = now();
            }

            $order->save();

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'actor_id' => $request->user()->id,
                'old_status' => $current,
                'new_status' => $target,
                'note' => $request->input('note'),
            ]);
        });

        $order->load(['items.measurements', 'events.actor']);

        return response()->json([
            'data' => (new OrderDetailResource($order))
                ->withMeasurements($request->user()->can('viewMeasurements', $order))
                ->resolve($request),
            'message' => 'État de la demande mis à jour.',
        ]);
    }
}
