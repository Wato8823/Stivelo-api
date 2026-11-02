<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? (bool) $this->user()?->can('update', $category)
            : (bool) $this->user()?->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $uniqueSlug = Rule::unique('categories', 'slug');

        if ($category instanceof Category) {
            $uniqueSlug = $uniqueSlug->ignore($category->id);
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'slug.regex' => 'Le slug ne peut contenir que des minuscules, des chiffres et des tirets.',
            'slug.unique' => 'Une catégorie utilise déjà ce slug.',
        ];
    }
}
