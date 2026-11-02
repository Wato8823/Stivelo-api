<?php

namespace Tests\Unit;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\OrderMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMessageBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_message_reprend_les_choix_la_reference_et_les_mesures(): void
    {
        $order = Order::factory()->create([
            'reference' => 'STV-2026-00A31',
            'status' => OrderStatus::PendingConfirmation,
            'channel' => OrderChannel::Whatsapp,
            'total_estimate' => 153_000,
            'customer_name' => 'Awa Ngo',
            'customer_phone' => '+237690112233',
            'customer_city' => 'Douala',
        ]);

        $item = OrderItem::factory()->for($order)->customSize()->create([
            'product_snapshot' => [
                'name' => 'Costume Signature',
                'color' => 'Noir',
                'fabric' => 'Lin lavé',
                'size' => 'Sur mesure',
            ],
            'unit_price' => 153_000,
            'line_total' => 153_000,
        ]);

        Measurement::factory()->for($item, 'orderItem')->create([
            'key' => 'chest_cm',
            'label' => 'Tour de poitrine',
            'value' => 98.5,
            'unit' => 'cm',
        ]);

        $message = OrderMessageBuilder::forOrder($order->fresh(['items.measurements']));

        $this->assertStringContainsString('Je souhaite commander les pièces suivantes', $message);
        $this->assertStringContainsString('Costume Signature — Noir · Lin lavé · Sur mesure', $message);
        $this->assertStringContainsString('Total indicatif : 153 000 FCFA', $message);
        $this->assertStringContainsString('Tour de poitrine : 98,5 cm', $message);
        $this->assertStringContainsString('Référence : STV-2026-00A31', $message);
        $this->assertStringContainsString('Ville : Douala', $message);
    }

    public function test_les_mesures_entieres_sont_affichees_sans_decimale(): void
    {
        $this->assertSame('98', OrderMessageBuilder::formatValue(98.0));
        $this->assertSame('98,5', OrderMessageBuilder::formatValue(98.5));
        $this->assertSame('100', OrderMessageBuilder::formatValue(100));
    }
}
