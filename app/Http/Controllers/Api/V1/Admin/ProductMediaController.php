<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductMediaRequest;
use App\Http\Resources\ProductMediaResource;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductMediaController extends Controller
{
    /**
     * POST /api/v1/admin/products/{product}/media — téléversement d'une photo.
     */
    public function store(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $disk = (string) config('catalog.media.disk', 'public');
        $file = $request->file('image');
        $key = $file->store('products/'.$product->id, $disk);
        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = $product->media()->create([
            'disk' => $disk,
            'storage_key' => $key,
            'alt' => $request->string('alt')->value(),
            'sort_order' => (int) $request->integer('sort_order', (int) $product->media()->max('sort_order') + 1),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ]);

        CatalogCache::flush();

        return response()->json([
            'data' => (new ProductMediaResource($media))->resolve($request),
            'message' => 'Photo ajoutée.',
        ], 201);
    }

    /**
     * DELETE /api/v1/admin/products/{product}/media/{media}
     */
    public function destroy(Request $request, Product $product, ProductMedia $media): JsonResponse
    {
        $this->authorize('update', $product);

        abort_unless($media->product_id === $product->id, 404, 'Cette photo n’appartient pas à ce modèle.');

        Storage::disk($media->disk)->delete($media->storage_key);
        $media->delete();

        CatalogCache::flush();

        return response()->json(['message' => 'Photo supprimée.']);
    }
}
