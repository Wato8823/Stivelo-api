<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'Cette adresse e-mail n’est pas valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',
        ];
    }

    /**
     * Nom du jeton Sanctum : identifie l'appareil connecté.
     */
    public function deviceName(): string
    {
        return $this->string('device_name')->trim()->value()
            ?: mb_substr((string) $this->userAgent(), 0, 60)
            ?: 'administration';
    }
}
