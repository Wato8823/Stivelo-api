<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && (bool) $this->user()?->can('update', $product);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('catalog.media.max_kb', 4096);
        $mimes = implode(',', config('catalog.media.mimes', ['jpg', 'jpeg', 'png', 'webp']));

        return [
            'image' => ['required', 'file', 'image', 'mimes:'.$mimes, 'max:'.$maxKb],
            'alt' => ['required', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Choisissez une image à téléverser.',
            'image.image' => 'Le fichier envoyé n’est pas une image valide.',
            'image.mimes' => 'Formats acceptés : JPG, PNG, WebP ou AVIF.',
            'image.max' => 'L’image ne doit pas dépasser '.config('catalog.media.max_kb').' Ko.',
            'alt.required' => 'Le texte alternatif est obligatoire (accessibilité).',
        ];
    }
}
