<?php

namespace App\Http\Resources\Admin;

use App\Enums\ProductOptionType;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MeasurementFieldResource;
use App\Http\Resources\ProductMediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle vu depuis l'administration : tous les champs, y compris brouillons,
 * options inactives et guide des mesures.
 *
 * @mixin \App\Models\Product
 */
class ProductAdminResource extends JsonResource
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
            'description' => $this->description,
            'collection_badge' => $this->collection_badge,
            'base_price' => $this->base_price,
            'price_label' => $this->formattedPrice(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_featured' => $this->is_featured,
            'custom_size_enabled' => $this->custom_size_enabled,
            'sort_order' => $this->sort_order,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
            'colors' => $this->optionRows(ProductOptionType::Color),
            'fabrics' => $this->optionRows(ProductOptionType::Fabric),
            'sizes' => $this->optionRows(ProductOptionType::Size),
            'measurement_fields' => MeasurementFieldResource::collection($this->whenLoaded('measurementFields')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function optionRows(ProductOptionType $type): array
    {
        if (! $this->relationLoaded('options')) {
            return [];
        }

        return $this->options
            ->filter(fn ($option) => $option->type === $type)
            ->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'value' => $option->value,
                'price_delta' => $option->price_delta,
                'sort_order' => $option->sort_order,
                'is_active' => $option->is_active,
            ])
            ->values()
            ->all();
    }
}
