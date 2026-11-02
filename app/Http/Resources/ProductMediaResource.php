<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductMedia
 */
class ProductMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'alt' => $this->alt,
            'sort_order' => $this->sort_order,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
