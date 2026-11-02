<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle tel qu'affiché dans une liste de catalogue.
 *
 * @mixin \App\Models\Product
 */
class ProductSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'collection_badge' => $this->collection_badge,
            'base_price' => $this->base_price,
            'price_label' => $this->formattedPrice(),
            'is_featured' => $this->is_featured,
            'custom_size_enabled' => $this->custom_size_enabled,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'thumbnail' => $this->whenLoaded(
                'media',
                fn () => $this->media->first() ? new ProductMediaResource($this->media->first()) : null,
            ),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
