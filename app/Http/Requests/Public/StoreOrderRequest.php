<?php

namespace App\Http\Requests\Public;

use App\Enums\ProductOptionType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation d'une demande de commande.
 *
 * Deux niveaux :
 *  1. contrôles de forme (champs, types, longueurs, consentement) ;
 *  2. contrôles métier (options réellement proposées par le modèle, taille
 *     disponible, mesures obligatoires et bornées) ajoutés dans after().
 *
 * Le prix n'est jamais accepté depuis le client : il est recalculé par
 * App\Services\OrderService à partir du catalogue.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $min = (int) config('catalog.measurements.min', 20);
        $max = (int) config('catalog.measurements.max', 250);

        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'min:2', 'max:120'],
            'customer.phone' => ['required', 'string', 'regex:/^\+?[0-9\s.\-()]{8,20}$/'],
            'customer.email' => ['nullable', 'email', 'max:160'],
            'customer.city' => ['nullable', 'string', 'max:120'],
            'customer.note' => ['nullable', 'string', 'max:1000'],
            'consent' => ['required', 'accepted'],

            'items' => ['required', 'array', 'min:1', 'max:5'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.color' => ['required', 'string', 'max:60'],
            'items.*.fabric' => ['required', 'string', 'max:60'],
            'items.*.size' => ['required', 'string', 'max:60'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'items.*.measurements' => ['nullable', 'array'],
            'items.*.measurements.*' => ['numeric', 'between:'.$min.','.$max],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.required' => 'Merci d’indiquer vos coordonnées.',
            'customer.name.required' => 'Le nom est obligatoire.',
            'customer.phone.required' => 'Le numéro de téléphone est obligatoire.',
            'customer.phone.regex' => 'Le numéro de téléphone n’est pas valide (format attendu : +237 6 XX XX XX XX).',
            'consent.required' => 'Vous devez accepter l’usage de vos coordonnées et mensurations pour envoyer la demande.',
            'consent.accepted' => 'Vous devez accepter l’usage de vos coordonnées et mensurations pour envoyer la demande.',
            'items.required' => 'Votre demande doit contenir au moins un modèle.',
            'items.max' => 'Une demande ne peut pas contenir plus de 5 modèles.',
            'items.*.product_id.exists' => 'Ce modèle n’existe plus ou n’est plus disponible.',
            'items.*.color.required' => 'La couleur est obligatoire.',
            'items.*.fabric.required' => 'Le tissu est obligatoire.',
            'items.*.size.required' => 'La taille est obligatoire.',
            'items.*.measurements.*.between' => 'Les mesures doivent être comprises entre :min et :max cm.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $items = $this->input('items', []);
                $products = Product::query()
                    ->published()
                    ->with(['options', 'measurementFields'])
                    ->whereIn('id', collect($items)->pluck('product_id')->all())
                    ->get()
                    ->keyBy('id');

                foreach ($items as $index => $item) {
                    $product = $products->get((int) $item['product_id']);

                    if ($product === null) {
                        $validator->errors()->add(
                            "items.{$index}.product_id",
                            'Ce modèle n’est plus publié. Merci de rafraîchir le catalogue.',
                        );

                        continue;
                    }

                    $this->validateOptions($validator, $index, $product, $item);
                    $this->validateMeasurements($validator, $index, $product, $item);
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateOptions(Validator $validator, int $index, Product $product, array $item): void
    {
        $color = $product->options->first(fn ($option) => $option->type === ProductOptionType::Color
            && $option->value === $item['color']
            && $option->is_active);

        if ($color === null) {
            $validator->errors()->add(
                "items.{$index}.color",
                'Cette couleur n’est pas proposée pour ce modèle.',
            );
        }

        $fabric = $product->options->first(fn ($option) => $option->type === ProductOptionType::Fabric
            && $option->value === $item['fabric']
            && $option->is_active);

        if ($fabric === null) {
            $validator->errors()->add(
                "items.{$index}.fabric",
                'Ce tissu n’est pas proposé pour ce modèle.',
            );
        }

        $isCustom = $item['size'] === 'Sur mesure';

        if ($isCustom && ! $product->custom_size_enabled) {
            $validator->errors()->add(
                "items.{$index}.size",
                'Ce modèle n’est pas disponible en sur-mesure.',
            );

            return;
        }

        if (! $isCustom) {
            $size = $product->options->first(fn ($option) => $option->type === ProductOptionType::Size
                && $option->value === $item['size']
                && $option->is_active);

            if ($size === null) {
                $validator->errors()->add(
                    "items.{$index}.size",
                    'Cette taille n’est pas disponible pour ce modèle.',
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateMeasurements(Validator $validator, int $index, Product $product, array $item): void
    {
        if (($item['size'] ?? null) !== 'Sur mesure') {
            return;
        }

        /** @var array<string, float|int|string> $measurements */
        $measurements = $item['measurements'] ?? [];
        $requiredFields = $product->measurementFields->where('is_required', true);

        if ($requiredFields->isEmpty()) {
            $validator->errors()->add(
                "items.{$index}.measurements",
                'Aucune mesure n’est configurée pour ce modèle : contactez la boutique.',
            );

            return;
        }

        foreach ($requiredFields as $field) {
            $value = $measurements[$field->key] ?? null;

            if ($value === null || $value === '') {
                $validator->errors()->add(
                    "items.{$index}.measurements.{$field->key}",
                    "La mesure « {$field->label} » est obligatoire pour le sur-mesure.",
                );

                continue;
            }

            $numeric = (float) $value;

            if ($numeric < $field->min_cm || $numeric > $field->max_cm) {
                $validator->errors()->add(
                    "items.{$index}.measurements.{$field->key}",
                    "La mesure « {$field->label} » doit être comprise entre {$field->min_cm} et {$field->max_cm} cm.",
                );
            }
        }

        $knownKeys = $product->measurementFields->pluck('key')->all();

        foreach (array_keys($measurements) as $key) {
            if (! in_array($key, $knownKeys, true)) {
                $validator->errors()->add(
                    "items.{$index}.measurements.{$key}",
                    'Cette mesure n’est pas demandée pour ce modèle.',
                );
            }
        }
    }
}
