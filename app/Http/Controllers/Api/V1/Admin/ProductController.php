<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProductOptionType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductIndexRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\ProductAdminResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/v1/admin/products — catalogue complet, brouillons compris.
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()
            ->with(['category', 'media', 'options', 'measurementFields'])
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->search($request->input('q'));

        if ($request->filled('category')) {
            $categoryId = Category::query()->where('slug', $request->input('category'))->value('id');
            $query->forCategory((int) $categoryId);
        }

        $products = $query->orderByDesc('updated_at')->paginate(
            (int) $request->integer('per_page', 20),
            ['*'],
            'page',
            max(1, (int) $request->integer('page', 1)),
        )->withQueryString();

        return response()->json([
            'data' => ProductAdminResource::collection($products->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = DB::transaction(function () use ($request) {
            $attributes = $request->productAttributes();
            $attributes['slug'] = $this->uniqueSlug(
                $attributes['slug'] ?? Str::slug($attributes['name']),
            );
            $attributes['published_at'] = $attributes['status'] === ProductStatus::Published->value
                ? now()
                : null;

            $product = Product::query()->create($attributes);

            $this->syncOptions($product, ProductOptionType::Color, $request->input('colors', []));
            $this->syncOptions($product, ProductOptionType::Fabric, $request->input('fabrics', []));
            $this->syncOptions($product, ProductOptionType::Size, $request->input('sizes', []));
            $this->syncMeasurements($product, $request->input('measurement_fields', []));
            $this->storeImages($request, $product);

            return $product;
        });

        CatalogCache::flush();

        return response()->json([
            'data' => (new ProductAdminResource($product->load(['category', 'media', 'options', 'measurementFields'])))
                ->resolve($request),
            'message' => 'Modèle créé.',
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/admin/products/{product}
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        DB::transaction(function () use ($request, $product) {
            $attributes = $request->productAttributes();

            if (array_key_exists('slug', $attributes)) {
                $attributes['slug'] = $this->uniqueSlug($attributes['slug'], $product->id);
            }

            if (($attributes['status'] ?? null) === ProductStatus::Published->value && $product->published_at === null) {
                $attributes['published_at'] = now();
            }

            $product->update($attributes);

            if ($request->has('colors')) {
                $this->syncOptions($product, ProductOptionType::Color, $request->input('colors', []));
            }

            if ($request->has('fabrics')) {
                $this->syncOptions($product, ProductOptionType::Fabric, $request->input('fabrics', []));
            }

            if ($request->has('sizes')) {
                $this->syncOptions($product, ProductOptionType::Size, $request->input('sizes', []));
            }

            if ($request->has('measurement_fields')) {
                $this->syncMeasurements($product, $request->input('measurement_fields', []));
            }
        });

        CatalogCache::flush();

        return response()->json([
            'data' => (new ProductAdminResource($product->load(['category', 'media', 'options', 'measurementFields'])))
                ->resolve($request),
            'message' => 'Modèle mis à jour.',
        ]);
    }

    /**
     * DELETE /api/v1/admin/products/{product} — archivage logique.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->update(['status' => ProductStatus::Archived]);
        $product->delete();

        CatalogCache::flush();

        return response()->json(['message' => 'Modèle archivé.']);
    }

    private function storeImages(StoreProductRequest $request, Product $product): void
    {
        $disk = (string) config('catalog.media.disk', 'public');
        $alts = $request->input('image_alts', []);

        foreach ($request->file('images', []) as $position => $file) {
            $key = $file->store('products/'.$product->id, $disk);
            $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

            $product->media()->create([
                'disk' => $disk,
                'storage_key' => $key,
                'alt' => filled($alts[$position] ?? null) ? $alts[$position] : $product->name,
                'sort_order' => $position + 1,
                'width' => $dimensions[0],
                'height' => $dimensions[1],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncOptions(Product $product, ProductOptionType $type, array $rows): void
    {
        $values = [];

        foreach ($rows as $position => $row) {
            $values[] = $row['value'];

            $product->options()->updateOrCreate(
                ['type' => $type->value, 'value' => $row['value']],
                [
                    'label' => $row['label'],
                    'price_delta' => (int) ($row['price_delta'] ?? 0),
                    'sort_order' => $position + 1,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ],
            );
        }

        if ($values !== []) {
            // Les options retirées sont désactivées (et non supprimées) pour
            // ne pas casser l'historique des demandes déjà enregistrées.
            $product->options()
                ->where('type', $type->value)
                ->whereNotIn('value', $values)
                ->update(['is_active' => false]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncMeasurements(Product $product, array $rows): void
    {
        $keys = [];

        foreach ($rows as $position => $row) {
            $keys[] = $row['key'];

            $product->measurementFields()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'hint' => $row['hint'] ?? null,
                    'is_required' => (bool) ($row['required'] ?? true),
                    'min_cm' => (int) ($row['min_cm'] ?? config('catalog.measurements.min')),
                    'max_cm' => (int) ($row['max_cm'] ?? config('catalog.measurements.max')),
                    'sort_order' => $position + 1,
                ],
            );
        }

        if ($keys !== []) {
            $product->measurementFields()->whereNotIn('key', $keys)->delete();
        }
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'modele';
        $candidate = $base;
        $suffix = 2;

        while (Product::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
