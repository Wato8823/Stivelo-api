<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products — catalogue filtrable et paginé.
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $sort = $request->sortKey();
        $perPage = $request->perPage();
        $page = max(1, (int) $request->integer('page', 1));

        $query = Product::query()
            ->published()
            ->with(['category', 'media'])
            ->search($filters['q'] ?? null)
            ->priceBetween(
                isset($filters['min_price']) ? (int) $filters['min_price'] : null,
                isset($filters['max_price']) ? (int) $filters['max_price'] : null,
            );

        if (! empty($filters['category'])) {
            $categoryId = Category::query()->where('slug', $filters['category'])->value('id');
            $query->forCategory((int) $categoryId);
        }

        if (($filters['featured'] ?? null) !== null) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        match ($sort) {
            'price_asc' => $query->orderBy('base_price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('base_price')->orderBy('name'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $products = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        return response()->json([
            'data' => ProductSummaryResource::collection($products->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'currency' => config('catalog.currency.code'),
                'sort' => $sort,
            ],
            'links' => [
                'first' => $products->url(1),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
                'last' => $products->url($products->lastPage()),
            ],
        ]);
    }

    /**
     * GET /api/v1/products/{slug} — fiche complète (variantes, mesures, WhatsApp).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $product = CatalogCache::remember(
            'product.'.$slug,
            fn () => Product::query()
                ->published()
                ->where('slug', $slug)
                ->with(['category', 'media', 'options', 'measurementFields'])
                ->first(),
            600,
        );

        abort_if($product === null || $product->status !== ProductStatus::Published, 404, 'Ce modèle n’est pas disponible.');

        return response()->json([
            'data' => (new ProductResource($product))->resolve($request),
        ]);
    }
}
