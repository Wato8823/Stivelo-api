<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories — familles publiées, avec le nombre de modèles.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = CatalogCache::remember('categories.public', fn () => Category::query()
            ->active()
            ->ordered()
            ->withCount(['products' => fn ($query) => $query->published()])
            ->get(), 900);

        return response()->json([
            'data' => CategoryResource::collection($categories)->resolve($request),
            'meta' => [
                'count' => $categories->count(),
                'currency' => config('catalog.currency.code'),
            ],
        ]);
    }
}
