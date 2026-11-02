<?php

namespace App\Http\Resources;

use App\Enums\ProductOptionType;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche produit complète : galerie, variantes, guide des mesures et lien
 * WhatsApp prérempli (pour une simple question, avant toute commande).
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = $this->whenLoaded('options', fn () => $this->options->where('is_active', true));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'collection_badge' => $this->collection_badge,
            'base_price' => $this->base_price,
            'price_label' => $this->formattedPrice(),
            'currency' => config('catalog.currency.code'),
            'is_featured' => $this->is_featured,
            'custom_size_enabled' => $this->custom_size_enabled,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
            'options' => [
                'colors' => $options
                    ? ProductOptionResource::collection($options->where('type', ProductOptionType::Color)->values())
                    : [],
                'fabrics' => $options
                    ? ProductOptionResource::collection($options->where('type', ProductOptionType::Fabric)->values())
                    : [],
                'sizes' => $options
                    ? ProductOptionResource::collection($options->where('type', ProductOptionType::Size)->values())
                    : [],
            ],
            'measurement_guide' => MeasurementFieldResource::collection($this->whenLoaded('measurementFields')),
            'whatsapp_url' => $this->whatsAppUrl(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    private function whatsAppUrl(): string
    {
        return WhatsAppLinkBuilder::link([
            'Bonjour Stive Clothing,',
            '',
            'Je souhaite des informations sur : '.$this->name.'.',
            'Référence modèle : '.$this->slug,
            'Prix indicatif : '.$this->formattedPrice(),
        ]);
    }
}
