<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de la liste des demandes dans l'administration.
 *
 * @mixin \App\Models\Order
 */
class OrderListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'city' => $this->customer_city,
            ],
            'items_count' => $this->items->sum('quantity'),
            'total_estimate' => $this->total_estimate,
            'total_label' => $this->formattedTotal(),
            'has_custom_size' => $this->items->contains('custom_size', true),
            'created_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
