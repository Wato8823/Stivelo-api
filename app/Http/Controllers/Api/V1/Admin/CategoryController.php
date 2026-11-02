<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/admin/categories — toutes les catégories, actives ou non.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->ordered()
            ->withCount('products')
            ->get();

        return response()->json([
            'data' => CategoryResource::collection($categories)->resolve($request),
            'meta' => ['count' => $categories->count()],
        ]);
    }

    /**
     * POST /api/v1/admin/categories
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::query()->create($this->attributes($request, true));

        CatalogCache::flush();

        return response()->json([
            'data' => (new CategoryResource($category))->resolve($request),
            'message' => 'Catégorie créée.',
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/admin/categories/{category}
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($this->attributes($request, false));

        CatalogCache::flush();

        return response()->json([
            'data' => (new CategoryResource($category))->resolve($request),
            'message' => 'Catégorie mise à jour.',
        ]);
    }

    /**
     * DELETE /api/v1/admin/categories/{category}
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Cette catégorie contient encore des modèles. Déplacez-les ou archivez-les avant de la supprimer.',
                'code' => 'category_not_empty',
                'request_id' => request()->attributes->get('request_id'),
            ], 409);
        }

        $category->delete();

        CatalogCache::flush();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, bool $isCreation): array
    {
        $data = $request->validated();
        $slug = $data['slug'] ?? Str::slug($data['name']);

        return [
            'name' => $data['name'],
            // En mise à jour, le slug existant est conservé s'il n'est pas fourni :
            // les liens déjà publiés ne doivent pas casser.
            'slug' => $isCreation
                ? $this->uniqueSlug($slug)
                : ($data['slug'] ?? $request->route('category')?->slug ?? $slug),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];
    }

    private function uniqueSlug(string $slug): string
    {
        $base = Str::slug($slug) ?: 'categorie';
        $candidate = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
