<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * GET /api/v1/admin/stats — tableau de bord de la boutique.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $countsByStatus = Order::query()
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $revenue = (int) Order::query()
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::InProduction->value,
                OrderStatus::Ready->value,
                OrderStatus::Delivered->value,
            ])
            ->sum('total_estimate');

        return response()->json([
            'data' => [
                'orders' => [
                    'total' => array_sum($countsByStatus),
                    'by_status' => $countsByStatus,
                    'pending' => $countsByStatus[OrderStatus::PendingConfirmation->value] ?? 0,
                    'created_today' => Order::query()->whereDate('created_at', today())->count(),
                    'created_last_7_days' => Order::query()->where('created_at', '>=', now()->subDays(7))->count(),
                    'revenue_estimate' => $revenue,
                    'revenue_label' => number_format($revenue, 0, ',', ' ').' '.config('catalog.currency.label'),
                ],
                'catalogue' => [
                    'products_published' => Product::query()->where('status', ProductStatus::Published)->count(),
                    'products_draft' => Product::query()->where('status', ProductStatus::Draft)->count(),
                    'categories_active' => Category::query()->where('is_active', true)->count(),
                ],
            ],
        ]);
    }
}
