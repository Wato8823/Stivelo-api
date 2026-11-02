<?php

namespace Tests\Feature\Public;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMeasurementField;
use App\Models\ProductOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('catalog.rate_limits.orders_per_minute', 100);

        $category = Category::factory()->create(['slug' => 'costume-veste']);

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Costume Signature',
            'slug' => 'costume-signature',
            'base_price' => 145_000,
            'custom_size_enabled' => true,
        ]);

        ProductOption::factory()->for($this->product)->color()
            ->create(['label' => 'Noir', 'value' => 'noir', 'price_delta' => 0]);
        ProductOption::factory()->for($this->product)->fabric()
            ->create(['label' => 'Lin lavé', 'value' => 'lin-lave', 'price_delta' => 8_000]);
        ProductOption::factory()->for($this->product)->size('M')->create(['label' => 'M', 'value' => 'M']);
        ProductOption::factory()->for($this->product)->size('Sur mesure')->create(['label' => 'Sur mesure', 'value' => 'Sur mesure']);

        ProductMeasurementField::factory()->for($this->product)
            ->create(['key' => 'chest_cm', 'label' => 'Tour de poitrine', 'is_required' => true, 'min_cm' => 20, 'max_cm' => 250]);
        ProductMeasurementField::factory()->for($this->product)
            ->create(['key' => 'waist_cm', 'label' => 'Tour de taille', 'is_required' => false, 'min_cm' => 20, 'max_cm' => 250]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer' => [
                'name' => 'Awa Ngo Bassong',
                'phone' => '+237 690 11 22 33',
                'city' => 'Douala',
                'note' => 'Livraison avant le 10 décembre.',
            ],
            'consent' => true,
            'items' => [[
                'product_id' => $this->product->id,
                'color' => 'noir',
                'fabric' => 'lin-lave',
                'size' => 'M',
                'quantity' => 1,
            ]],
        ], $overrides);
    }

    public function test_une_demande_valide_est_enregistree_avec_une_reference_et_un_lien_whatsapp(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_confirmation')
            ->assertJsonStructure([
                'data' => ['reference', 'status', 'status_label', 'total_estimate', 'items', 'whatsapp_url'],
                'message',
            ]);

        $reference = $response->json('data.reference');
        $this->assertMatchesRegularExpression('/^STV-\d{4}-[A-Z0-9]{5}$/', $reference);

        $order = Order::query()->where('reference', $reference)->firstOrFail();
        $this->assertSame(OrderStatus::PendingConfirmation, $order->status);
        $this->assertSame('+237690112233', $order->customer_phone);
        $this->assertTrue($order->consent_given);
        $this->assertSame(153_000, $order->total_estimate); // 145 000 + 8 000 de tissu
        $this->assertSame('noir', $order->items->first()->product_snapshot['color_value']);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'new_status' => 'pending_confirmation']);

        $url = $response->json('data.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/237600000000?text=', $url);
        $this->assertStringContainsString('Costume%20Signature', $url);
        $this->assertStringContainsString($reference, $url);
    }

    public function test_le_prix_est_recalcule_par_le_serveur(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['unit_price'] = 1; // tentative d'imposer un prix
        $payload['total_estimate'] = 1;

        $this->postJson('/api/v1/orders', $payload)->assertStatus(201);

        $this->assertSame(153_000, Order::query()->firstOrFail()->total_estimate);
    }

    public function test_le_sur_mesure_enregistre_les_mensurations(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['size'] = 'Sur mesure';
        $payload['items'][0]['measurements'] = ['chest_cm' => 98.5, 'waist_cm' => 84];

        $response = $this->postJson('/api/v1/orders', $payload)->assertStatus(201);

        $order = Order::query()->where('reference', $response->json('data.reference'))->firstOrFail();
        $item = $order->items->first();

        $this->assertTrue($item->custom_size);
        $this->assertSame('Sur mesure', $item->product_snapshot['size']);
        $this->assertEqualsWithDelta(98.5, $item->measurements->firstWhere('key', 'chest_cm')->value, 0.01);
        $this->assertCount(2, $item->measurements);

        $this->assertStringContainsString('98%2C5%20cm', $response->json('data.whatsapp_url'));
    }

    public function test_une_mesure_obligatoire_manquante_bloque_la_demande(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['size'] = 'Sur mesure';
        $payload['items'][0]['measurements'] = ['waist_cm' => 84];

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['items.0.measurements.chest_cm']]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_une_mesure_hors_bornes_est_refusee(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['size'] = 'Sur mesure';
        $payload['items'][0]['measurements'] = ['chest_cm' => 400];

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items.0.measurements.chest_cm']]);
    }

    public function test_une_couleur_non_proposee_est_refusee(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['color'] = 'rose-fuchsia';

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items.0.color']]);
    }

    public function test_une_taille_non_proposee_est_refusee(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['size'] = 'XXXL';

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items.0.size']]);
    }

    public function test_le_sur_mesure_est_refuse_si_le_modele_ne_le_propose_pas(): void
    {
        $this->product->update(['custom_size_enabled' => false]);

        $payload = $this->payload();
        $payload['items'][0]['size'] = 'Sur mesure';
        $payload['items'][0]['measurements'] = ['chest_cm' => 98];

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items.0.size']]);
    }

    public function test_le_consentement_est_obligatoire(): void
    {
        $payload = $this->payload();
        $payload['consent'] = false;

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['consent']]);
    }

    public function test_un_numero_de_telephone_invalide_est_refuse(): void
    {
        $payload = $this->payload();
        $payload['customer']['phone'] = 'abc';

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['customer.phone']]);
    }

    public function test_un_modele_non_publie_ne_peut_pas_etre_commande(): void
    {
        $this->product->update(['status' => 'draft']);

        $this->postJson('/api/v1/orders', $this->payload())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items.0.product_id']]);
    }

    public function test_les_demandes_sont_limitees_en_debit(): void
    {
        config()->set('catalog.rate_limits.orders_per_minute', 2);

        $this->postJson('/api/v1/orders', $this->payload())->assertStatus(201);
        $this->postJson('/api/v1/orders', $this->payload())->assertStatus(201);
        $this->postJson('/api/v1/orders', $this->payload())
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }
}
