<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
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
        return [
            'category' => ['nullable', 'string', 'max:140', 'exists:categories,slug'],
            'q' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:10000000', 'gte:min_price'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'name'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.exists' => 'Cette catégorie n’existe pas ou n’est plus publiée.',
            'max_price.gte' => 'Le prix maximum doit être supérieur ou égal au prix minimum.',
            'per_page.max' => 'Vous ne pouvez demander que 48 modèles par page.',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 12);
    }

    public function sortKey(): string
    {
        return (string) $this->input('sort', 'newest');
    }
}
