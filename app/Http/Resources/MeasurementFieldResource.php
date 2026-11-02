<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche du guide des mesures (une ligne du formulaire « Sur mesure »).
 *
 * @mixin \App\Models\ProductMeasurementField
 */
class MeasurementFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'hint' => $this->hint,
            'required' => $this->is_required,
            'min_cm' => $this->min_cm,
            'max_cm' => $this->max_cm,
            'unit' => config('catalog.measurements.unit', 'cm'),
        ];
    }
}
