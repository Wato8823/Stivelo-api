<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductMeasurementField;
use App\Models\ProductOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $attributes = []): Product
    {
        $category = Category::factory()->create(['name' => 'Costume et veste', 'slug' => 'costume-veste']);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Costume Signature',
            'slug' => 'costume-signature',
            'base_price' => 145_000,
        ], $attributes));
    }

    public function test_les_categories_publiees_sont_listees_avec_leur_nombre_de_modeles(): void
    {
        $active = Category::factory()->create(['name' => 'Chemise', 'slug' => 'chemise', 'sort_order' => 1]);
        Category::factory()->inactive()->create(['slug' => 'brouillon']);
        Product::factory()->create(['category_id' => $active->id]);
        Product::factory()->draft()->create(['category_id' => $active->id]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'chemise')
            ->assertJsonPath('data.0.products_count', 1);
    }

    public function test_le_catalogue_ne_montre_que_les_modeles_publies(): void
    {
        $this->makeProduct();
        $this->makeProduct(['slug' => 'modele-brouillon', 'status' => 'draft', 'published_at' => null]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'costume-signature')
            ->assertJsonPath('data.0.price_label', '145 000 FCFA');
    }

    public function test_les_filtres_categorie_recherche_et_prix_sont_appliques(): void
    {
        $this->makeProduct(['name' => 'Costume Signature', 'base_price' => 145_000]);

        $this->getJson('/api/v1/products?category=costume-veste&q=costume&min_price=100000&max_price=200000')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/products?q=chemise')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/products?min_price=200000')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_un_filtre_invalide_renvoie_une_erreur_lisible(): void
    {
        $this->getJson('/api/v1/products?max_price=1000&min_price=5000')
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['max_price']]);
    }

    public function test_la_fiche_produit_expose_variantes_mesures_et_lien_whatsapp(): void
    {
        $product = $this->makeProduct(['custom_size_enabled' => true]);

        ProductOption::factory()->for($product)->color()->create(['label' => 'Noir', 'value' => 'noir']);
        ProductOption::factory()->for($product)->fabric()->create(['label' => 'Lin lavé', 'value' => 'lin-lave', 'price_delta' => 8000]);
        ProductOption::factory()->for($product)->size('M')->create();
        ProductMedia::factory()->for($product)->create(['disk' => 'demo', 'storage_key' => 'products/placeholder-1.png']);
        ProductMeasurementField::factory()->for($product)->create(['key' => 'chest_cm', 'label' => 'Tour de poitrine']);

        $response = $this->getJson('/api/v1/products/costume-signature')
            ->assertOk()
            ->assertJsonPath('data.slug', 'costume-signature')
            ->assertJsonCount(1, 'data.options.colors')
            ->assertJsonPath('data.options.fabrics.0.price_delta_label', '+8 000 FCFA')
            ->assertJsonPath('data.measurement_guide.0.key', 'chest_cm');

        $this->assertStringStartsWith('https://wa.me/237600000000?text=', $response->json('data.whatsapp_url'));
    }

    public function test_une_fiche_inconnue_ou_non_publiee_renvoie_404(): void
    {
        $this->makeProduct(['slug' => 'modele-cache', 'status' => 'draft', 'published_at' => null]);

        $this->getJson('/api/v1/products/modele-cache')
            ->assertStatus(404)
            ->assertJsonPath('code', 'not_found');

        $this->getJson('/api/v1/products/inconnu')
            ->assertStatus(404)
            ->assertJsonPath('code', 'not_found');
    }

    public function test_la_configuration_boutique_expose_le_numero_whatsapp_et_la_devise(): void
    {
        $this->getJson('/api/v1/shop')
            ->assertOk()
            ->assertJsonPath('data.whatsapp.number', '237600000000')
            ->assertJsonPath('data.currency.code', 'XAF')
            ->assertJsonPath('data.measurements.unit', 'cm')
            ->assertJsonStructure(['data' => ['order_statuses', 'sizes', 'endpoints']]);
    }

    public function test_la_sante_de_l_api_repond(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
    }
}
