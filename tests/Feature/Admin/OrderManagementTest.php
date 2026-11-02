<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    private function makeOrder(array $attributes = []): Order
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $order = Order::factory()->create($attributes);
        $item = OrderItem::factory()->for($order)->for($product)->customSize()->create();

        Measurement::factory()->for($item, 'orderItem')->create([
            'key' => 'chest_cm',
            'label' => 'Tour de poitrine',
            'value' => 102,
        ]);

        return $order->fresh(['items.measurements']);
    }

    public function test_un_conseiller_consulte_la_liste_des_demandes(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $this->makeOrder(['status' => OrderStatus::PendingConfirmation]);

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.counts_by_status.pending_confirmation', 1)
            ->assertJsonStructure(['data' => [['reference', 'status', 'customer', 'items_count', 'has_custom_size']]]);
    }

    public function test_la_liste_est_filtrable_par_statut_et_recherche(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $this->makeOrder(['status' => OrderStatus::PendingConfirmation, 'customer_name' => 'Awa Ngo']);
        $this->makeOrder(['status' => OrderStatus::Cancelled, 'customer_name' => 'Serge Mbappe']);

        $this->getJson('/api/v1/admin/orders?status=cancelled')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer.name', 'Serge Mbappe');

        $this->getJson('/api/v1/admin/orders?q=awa')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_le_dossier_contient_les_mesures_pour_les_roles_autorises(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $order = $this->makeOrder();

        $response = $this->getJson('/api/v1/admin/orders/'.$order->id)->assertOk();

        $response->assertJsonPath('data.items.0.measurements.0.key', 'chest_cm')
            ->assertJsonPath('data.items.0.measurements.0.value', 102.0)
            ->assertJsonStructure(['data' => ['events', 'allowed_next_statuses'], 'whatsapp_url']);
    }

    public function test_un_changement_de_statut_valide_est_journalise(): void
    {
        $advisor = $this->actingAsRole(UserRole::Advisor);
        $order = $this->makeOrder(['status' => OrderStatus::PendingConfirmation]);

        $this->patchJson('/api/v1/admin/orders/'.$order->id, [
            'status' => 'confirmed',
            'note' => 'Prix et délai confirmés par téléphone.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'actor_id' => $advisor->id,
            'old_status' => 'pending_confirmation',
            'new_status' => 'confirmed',
        ]);
    }

    public function test_une_transition_interdite_renvoie_409(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $order = $this->makeOrder(['status' => OrderStatus::PendingConfirmation]);

        $this->patchJson('/api/v1/admin/orders/'.$order->id, ['status' => 'delivered'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_status_transition');

        $this->assertSame(OrderStatus::PendingConfirmation, $order->fresh()->status);
    }

    public function test_un_statut_inconnu_est_refuse(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $order = $this->makeOrder();

        $this->patchJson('/api/v1/admin/orders/'.$order->id, ['status' => 'expediee'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_l_atelier_peut_consulter_mais_pas_changer_un_statut(): void
    {
        $this->actingAsRole(UserRole::Atelier);
        $order = $this->makeOrder();

        $this->getJson('/api/v1/admin/orders/'.$order->id)->assertOk();

        $this->patchJson('/api/v1/admin/orders/'.$order->id, ['status' => 'confirmed'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_un_visiteur_ne_peut_pas_acceder_a_l_administration(): void
    {
        $order = $this->makeOrder();

        $this->getJson('/api/v1/admin/orders')->assertStatus(401);
        $this->getJson('/api/v1/admin/orders/'.$order->id)->assertStatus(401);
    }

    public function test_le_tableau_de_bord_agrege_les_demandes(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $this->makeOrder(['status' => OrderStatus::PendingConfirmation]);
        $this->makeOrder(['status' => OrderStatus::Confirmed, 'total_estimate' => 150_000]);

        $this->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.orders.total', 2)
            ->assertJsonPath('data.orders.pending', 1)
            ->assertJsonPath('data.orders.revenue_estimate', 150_000);
    }
}
