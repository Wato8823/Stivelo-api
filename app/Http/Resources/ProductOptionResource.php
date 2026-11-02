<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductOption
 */
class ProductOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->label,
            'value' => $this->value,
            'price_delta' => $this->price_delta,
            'price_delta_label' => $this->price_delta > 0
                ? '+'.number_format($this->price_delta, 0, ',', ' ').' '.config('catalog.currency.label')
                : null,
        ];
    }
}
