<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Trois demandes de démonstration pour visualiser l'écran de suivi :
 * une demande neuve, une confirmée puis en confection, une livrée.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->with('options')->get()->keyBy('slug');
        $advisor = User::query()->where('role', 'advisor')->first();

        if ($products->isEmpty()) {
            return;
        }

        $samples = [
            [
                'customer_name' => 'Awa Ngo Bassong',
                'customer_phone' => '+237690112233',
                'customer_city' => 'Douala',
                'product' => 'costume-signature-deux-pieces',
                'color' => 'Noir',
                'fabric' => 'Laine froide',
                'size' => 'Sur mesure',
                'status' => OrderStatus::PendingConfirmation,
                'measurements' => ['chest_cm' => 102, 'waist_cm' => 88, 'hip_cm' => 100, 'shoulder_cm' => 46, 'sleeve_cm' => 62],
                'note' => 'Mariage le 20 décembre, livraison souhaitée avant le 10.',
                'age_days' => 1,
            ],
            [
                'customer_name' => 'Serge Mbappe',
                'customer_phone' => '+237677889900',
                'customer_city' => 'Yaoundé',
                'product' => 'chemise-lin-riviera',
                'color' => 'Olive',
                'fabric' => 'Lin lavé',
                'size' => 'L',
                'status' => OrderStatus::InProduction,
                'measurements' => [],
                'note' => null,
                'age_days' => 6,
            ],
            [
                'customer_name' => 'Clarisse Ewane',
                'customer_phone' => '+237655443322',
                'customer_city' => 'Bafoussam',
                'product' => 'ensemble-wax-bogolan',
                'color' => 'Écru',
                'fabric' => 'Wax premium',
                'size' => 'Sur mesure',
                'status' => OrderStatus::Delivered,
                'measurements' => ['chest_cm' => 94, 'waist_cm' => 78, 'hip_cm' => 98, 'inseam_cm' => 74, 'sleeve_cm' => 58],
                'note' => 'Cadeau de cérémonie.',
                'age_days' => 21,
            ],
        ];

        foreach ($samples as $sample) {
            $product = $products[$sample['product']] ?? $products->first();
            $fabricDelta = (int) $product->options
                ->first(fn ($option) => $option->value === $sample['fabric'])?->price_delta;
            $unitPrice = $product->base_price + $fabricDelta;

            $order = Order::query()->firstOrNew([
                'customer_phone' => $sample['customer_phone'],
                'customer_name' => $sample['customer_name'],
            ]);

            // La référence n'est générée qu'à la création : un nouveau passage
            // du seeder ne doit pas réécrire l'historique existant.
            if (! $order->exists) {
                $order->reference = Order::newReference();
            }

            $order->fill(
                [
                    'reference' => $order->reference,
                    'status' => $sample['status'],
                    'total_estimate' => $unitPrice,
                    'customer_city' => $sample['customer_city'],
                    'customer_note' => $sample['note'],
                    'channel' => 'whatsapp',
                    'consent_given' => true,
                    'confirmed_at' => $sample['status'] === OrderStatus::PendingConfirmation ? null : now()->subDays($sample['age_days'])->addHours(3),
                ],
            )->save();

            // La date de création est forcée pour que la démonstration couvre
            // plusieurs jours d'activité (created_at n'est pas assignable en masse).
            $order->forceFill(['created_at' => now()->subDays($sample['age_days'])])->saveQuietly();

            $item = OrderItem::query()->updateOrCreate(
                ['order_id' => $order->id, 'product_id' => $product->id],
                [
                    'product_snapshot' => [
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'base_price' => $product->base_price,
                        'color' => $sample['color'],
                        'fabric' => $sample['fabric'],
                        'size' => $sample['size'],
                        'price_delta' => $fabricDelta,
                    ],
                    'unit_price' => $unitPrice,
                    'quantity' => 1,
                    'line_total' => $unitPrice,
                    'custom_size' => $sample['size'] === 'Sur mesure',
                ],
            );

            foreach ($sample['measurements'] as $key => $value) {
                $item->measurements()->updateOrCreate(
                    ['key' => $key],
                    ['label' => $this->labelFor($key), 'value' => $value, 'unit' => 'cm'],
                );
            }

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'actor_id' => null,
                'old_status' => null,
                'new_status' => OrderStatus::PendingConfirmation,
                'note' => 'Demande reçue depuis la boutique.',
            ]);

            if ($sample['status'] !== OrderStatus::PendingConfirmation) {
                foreach ($this->historyFor($sample['status']) as $index => $status) {
                    OrderEvent::query()->create([
                        'order_id' => $order->id,
                        'actor_id' => $advisor?->id,
                        'old_status' => $index === 0 ? OrderStatus::PendingConfirmation : $this->historyFor($sample['status'])[$index - 1],
                        'new_status' => $status,
                        'note' => null,
                    ]);
                }
            }
        }
    }

    /**
     * @return list<OrderStatus>
     */
    private function historyFor(OrderStatus $target): array
    {
        return match ($target) {
            OrderStatus::Confirmed => [OrderStatus::Confirmed],
            OrderStatus::InProduction => [OrderStatus::Confirmed, OrderStatus::InProduction],
            OrderStatus::Ready => [OrderStatus::Confirmed, OrderStatus::InProduction, OrderStatus::Ready],
            OrderStatus::Delivered => [OrderStatus::Confirmed, OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::Delivered],
            default => [],
        };
    }

    private function labelFor(string $key): string
    {
        return [
            'chest_cm' => 'Tour de poitrine',
            'waist_cm' => 'Tour de taille',
            'hip_cm' => 'Tour de hanches',
            'shoulder_cm' => 'Largeur d’épaules',
            'sleeve_cm' => 'Longueur de manche',
            'inseam_cm' => 'Longueur entrejambe',
            'neck_cm' => 'Tour de cou',
            'thigh_cm' => 'Tour de cuisse',
            'height_cm' => 'Taille totale',
        ][$key] ?? $key;
    }
}
