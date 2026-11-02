<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse publique après création d'une demande : aucun identifiant
 * technique n'est renvoyé, seulement la référence, l'état, le récapitulatif
 * et le lien WhatsApp prérempli.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    private ?string $whatsAppUrl = null;

    public function withWhatsAppUrl(?string $url): static
    {
        $this->whatsAppUrl = $url;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'channel' => $this->channel->value,
            'total_estimate' => $this->total_estimate,
            'total_label' => $this->formattedTotal(),
            'currency' => $this->currency,
            'items' => $this->items->map(fn ($item) => [
                'summary' => $item->summary(),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'custom_size' => $item->custom_size,
            ])->all(),
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'city' => $this->customer_city,
            ],
            'whatsapp_url' => $this->whatsAppUrl,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
