<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\ProductOptionType;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\OrderMessageBuilder;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier de la demande de commande.
 *
 * - le prix est recalculé à partir du catalogue (jamais accepté du client) ;
 * - chaque ligne conserve un instantané complet des options et du prix ;
 * - les mensurations ne sont conservées que pour un « Sur mesure » ;
 * - la référence publique est unique ;
 * - un message WhatsApp prérempli est renvoyé au client.
 */
class OrderService
{
    /**
     * @param  array<string, mixed>  $payload  données validées par StoreOrderRequest
     * @return array{order: Order, whatsapp_url: string}
     */
    public function createFromPayload(array $payload, Request $request): array
    {
        $order = DB::transaction(function () use ($payload, $request) {
            $items = $this->resolveItems($payload['items']);

            $order = Order::query()->create([
                'reference' => $this->uniqueReference(),
                'customer_name' => trim((string) $payload['customer']['name']),
                'customer_phone' => $this->normalizePhone((string) $payload['customer']['phone']),
                'customer_email' => $payload['customer']['email'] ?? null,
                'customer_city' => $payload['customer']['city'] ?? null,
                'customer_note' => $payload['customer']['note'] ?? null,
                'status' => OrderStatus::PendingConfirmation,
                'total_estimate' => array_sum(array_column($items, 'line_total')),
                'currency' => config('catalog.currency.code'),
                'channel' => OrderChannel::Whatsapp,
                'consent_given' => true,
                'source_ip_hash' => $this->hashIp($request->ip()),
            ]);

            foreach ($items as $item) {
                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_snapshot' => $item['snapshot'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $item['line_total'],
                    'custom_size' => $item['custom_size'],
                ]);

                foreach ($item['measurements'] as $key => $measurement) {
                    $orderItem->measurements()->create([
                        'key' => $key,
                        'label' => $measurement['label'],
                        'value' => $measurement['value'],
                        'unit' => config('catalog.measurements.unit', 'cm'),
                    ]);
                }
            }

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'actor_id' => null,
                'old_status' => null,
                'new_status' => OrderStatus::PendingConfirmation,
                'note' => 'Demande créée depuis la boutique.',
            ]);

            return $order->load(['items.measurements']);
        });

        $message = OrderMessageBuilder::forOrder($order);

        return [
            'order' => $order,
            'whatsapp_url' => WhatsAppLinkBuilder::linkForText($message),
        ];
    }

    /**
     * Recalcule prix, instantané et mesures de chaque ligne à partir du
     * catalogue, puis vérifie la cohérence des options.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function resolveItems(array $items): array
    {
        $products = Product::query()
            ->published()
            ->with(['options', 'measurementFields'])
            ->whereIn('id', collect($items)->pluck('product_id')->all())
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($items as $index => $item) {
            $product = $products->get((int) $item['product_id']);

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Ce modèle n’est plus disponible.',
                ]);
            }

            $color = $this->findOption($product, ProductOptionType::Color, (string) $item['color']);
            $fabric = $this->findOption($product, ProductOptionType::Fabric, (string) $item['fabric']);
            $isCustomSize = $item['size'] === 'Sur mesure';
            $size = $isCustomSize
                ? null
                : $this->findOption($product, ProductOptionType::Size, (string) $item['size']);

            if ($color === null || $fabric === null || (! $isCustomSize && $size === null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.size" => 'Les options choisies ne sont plus disponibles pour ce modèle.',
                ]);
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (int) $product->base_price
                + (int) $color->price_delta
                + (int) $fabric->price_delta
                + (int) ($size?->price_delta ?? 0);

            $resolved[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
                'custom_size' => $isCustomSize,
                'measurements' => $isCustomSize
                    ? $this->resolveMeasurements($product, $item['measurements'] ?? [])
                    : [],
                'snapshot' => [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'base_price' => $product->base_price,
                    'color' => $color->label,
                    'color_value' => $color->value,
                    'fabric' => $fabric->label,
                    'fabric_value' => $fabric->value,
                    'fabric_price_delta' => $fabric->price_delta,
                    'size' => $isCustomSize ? 'Sur mesure' : $size->value,
                    'custom_size' => $isCustomSize,
                ],
            ];
        }

        return $resolved;
    }

    private function findOption(Product $product, ProductOptionType $type, string $value): ?\App\Models\ProductOption
    {
        return $product->options
            ->first(fn ($option) => $option->type === $type
                && $option->value === $value
                && $option->is_active);
    }

    /**
     * @param  array<string, float|int|string>  $measurements
     * @return array<string, array{label: string, value: float}>
     */
    private function resolveMeasurements(Product $product, array $measurements): array
    {
        $resolved = [];

        foreach ($product->measurementFields as $field) {
            $value = $measurements[$field->key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $resolved[$field->key] = [
                'label' => $field->label,
                'value' => round((float) $value, 1),
            ];
        }

        return $resolved;
    }

    private function uniqueReference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = Order::newReference();

            if (! Order::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        return Order::newReference().'-'.random_int(10, 99);
    }

    /**
     * Format international sans espaces ni séparateurs : +237690112233.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        return '+'.$digits;
    }

    /**
     * L'adresse IP n'est jamais conservée en clair (CDC §9) : seul un
     * condensat salé permet de repérer les abus.
     */
    private function hashIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
