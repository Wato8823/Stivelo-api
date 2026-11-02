<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderEvent>
 */
class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'actor_id' => null,
            'old_status' => OrderStatus::PendingConfirmation,
            'new_status' => OrderStatus::Confirmed,
            'note' => null,
        ];
    }

    public function by(User $actor): static
    {
        return $this->state(fn () => ['actor_id' => $actor->id]);
    }
}
