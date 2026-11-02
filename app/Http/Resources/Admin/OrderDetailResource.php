<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dossier complet d'une demande. Les mensurations ne sont incluses que si
 * l'utilisateur connecté y est habilité (OrderPolicy::viewMeasurements).
 *
 * @mixin \App\Models\Order
 */
class OrderDetailResource extends JsonResource
{
    private bool $includeMeasurements = false;

    public function withMeasurements(bool $include): static
    {
        $this->includeMeasurements = $include;

        return $this;
    }

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
            'allowed_next_statuses' => array_map(
                fn ($status) => ['value' => $status->value, 'label' => $status->label()],
                $this->status->allowedTransitions(),
            ),
            'channel' => $this->channel->value,
            'currency' => $this->currency,
            'total_estimate' => $this->total_estimate,
            'total_label' => $this->formattedTotal(),
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'email' => $this->customer_email,
                'city' => $this->customer_city,
                'note' => $this->customer_note,
                'consent_given' => $this->consent_given,
            ],
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'snapshot' => $item->product_snapshot,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'custom_size' => $item->custom_size,
                'measurements' => $this->includeMeasurements
                    ? $item->measurements->map(fn ($measurement) => [
                        'key' => $measurement->key,
                        'label' => $measurement->label,
                        'value' => $measurement->value,
                        'unit' => $measurement->unit,
                    ])->all()
                    : null,
            ])->all(),
            'events' => $this->events->map(fn ($event) => [
                'old_status' => $event->old_status?->value,
                'new_status' => $event->new_status->value,
                'new_status_label' => $event->new_status->label(),
                'note' => $event->note,
                'actor' => $event->actor?->name,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
