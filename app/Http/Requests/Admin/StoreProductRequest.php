<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / mise à jour d'un modèle. La mise à jour réutilise ces règles ;
 * UpdateProductRequest ajoute l'exception d'unicité sur le slug.
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? (bool) $this->user()?->can('update', $product)
            : (bool) $this->user()?->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slugUniqueRule()],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'collection_badge' => ['nullable', 'string', 'max:60'],
            'base_price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'status' => ['required', Rule::in(ProductStatus::values())],
            'is_featured' => ['nullable', 'boolean'],
            'custom_size_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'colors' => ['sometimes', 'array', 'max:30'],
            'colors.*.label' => ['required', 'string', 'max:120'],
            'colors.*.value' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9\-_ ]+$/'],
            'colors.*.price_delta' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'colors.*.is_active' => ['nullable', 'boolean'],

            'fabrics' => ['sometimes', 'array', 'max:30'],
            'fabrics.*.label' => ['required', 'string', 'max:120'],
            'fabrics.*.value' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9\-_ ]+$/'],
            'fabrics.*.price_delta' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'fabrics.*.is_active' => ['nullable', 'boolean'],

            'sizes' => ['sometimes', 'array', 'max:15'],
            'sizes.*.label' => ['required', 'string', 'max:60'],
            'sizes.*.value' => ['required', 'string', 'max:60'],
            'sizes.*.price_delta' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sizes.*.is_active' => ['nullable', 'boolean'],

            'measurement_fields' => ['sometimes', 'array', 'max:20'],
            'measurement_fields.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'measurement_fields.*.label' => ['required', 'string', 'max:120'],
            'measurement_fields.*.hint' => ['nullable', 'string', 'max:240'],
            'measurement_fields.*.required' => ['nullable', 'boolean'],
            'measurement_fields.*.min_cm' => ['nullable', 'integer', 'min:10', 'max:250'],
            'measurement_fields.*.max_cm' => ['nullable', 'integer', 'min:10', 'max:250', 'gte:measurement_fields.*.min_cm'],
        ];

        // Le téléversement groupé est disponible lors de la création seulement ;
        // les photos d'un modèle existant gardent leur endpoint dédié.
        if (! ($this->route('product') instanceof Product)) {
            $rules['images'] = ['sometimes', 'array', 'max:10'];
            $rules['images.*'] = [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', config('catalog.media.mimes', ['jpg', 'jpeg', 'png', 'webp'])),
                'max:'.(int) config('catalog.media.max_kb', 4096),
            ];
            $rules['image_alts'] = ['sometimes', 'array', 'max:10'];
            $rules['image_alts.*'] = ['nullable', 'string', 'max:180'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Cette catégorie n’existe pas.',
            'name.required' => 'Le nom du modèle est obligatoire.',
            'base_price.required' => 'Le prix indicatif est obligatoire.',
            'base_price.integer' => 'Le prix doit être un nombre entier de FCFA (sans centimes).',
            'slug.regex' => 'Le slug ne peut contenir que des minuscules, des chiffres et des tirets.',
            'slug.unique' => 'Ce slug est déjà utilisé par un autre modèle.',
            'status.in' => 'Ce statut est inconnu (draft, published ou archived).',
            'colors.*.value.regex' => 'La valeur technique d’une couleur ne peut contenir que lettres, chiffres, tirets et espaces.',
            'fabrics.*.value.regex' => 'La valeur technique d’un tissu ne peut contenir que lettres, chiffres, tirets et espaces.',
            'measurement_fields.*.key.regex' => 'La clé d’une mesure doit être en minuscules avec des underscores (ex. chest_cm).',
            'measurement_fields.*.max_cm.gte' => 'La borne haute d’une mesure doit être supérieure à sa borne basse.',
        ];
    }

    protected function slugUniqueRule(): mixed
    {
        $product = $this->route('product');
        $rule = Rule::unique('products', 'slug');

        // En mise à jour, le slug du modèle courant ne se concurrence pas.
        return $product instanceof Product ? $rule->ignore($product->id) : $rule;
    }

    /**
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $data = $this->validated();

        return array_filter([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'collection_badge' => $data['collection_badge'] ?? null,
            'base_price' => $data['base_price'],
            'status' => $data['status'],
            'is_featured' => $this->boolean('is_featured'),
            'custom_size_enabled' => $this->has('custom_size_enabled') ? $this->boolean('custom_size_enabled') : true,
            'sort_order' => $data['sort_order'] ?? 0,
        ], fn ($value) => $value !== null);
    }
}
