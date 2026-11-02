<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order|null $order */
        $order = $this->route('order');

        return $order !== null && (bool) $this->user()?->can('updateStatus', $order);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrderStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Le nouvel état est obligatoire.',
            'status.in' => 'Cet état de commande est inconnu.',
            'note.max' => 'La note ne peut pas dépasser 500 caractères.',
        ];
    }

    public function targetStatus(): OrderStatus
    {
        return OrderStatus::from((string) $this->input('status'));
    }
}
