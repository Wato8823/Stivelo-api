<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => 'Veste de Costume Milano',
            'short_description' => 'Veste seule, doublure légère.',
            'description' => 'Une veste droite à revers cranté.',
            'base_price' => 89_000,
            'status' => 'published',
            'is_featured' => true,
            'custom_size_enabled' => true,
            'colors' => [
                ['label' => 'Noir', 'value' => 'noir', 'price_delta' => 0],
                ['label' => 'Bleu nuit', 'value' => 'bleu-nuit', 'price_delta' => 0],
            ],
            'fabrics' => [
                ['label' => 'Laine froide', 'value' => 'laine-froide', 'price_delta' => 15_000],
            ],
            'sizes' => [
                ['label' => 'M', 'value' => 'M'],
                ['label' => 'Sur mesure', 'value' => 'Sur mesure'],
            ],
            'measurement_fields' => [
                ['key' => 'chest_cm', 'label' => 'Tour de poitrine', 'required' => true, 'min_cm' => 20, 'max_cm' => 250],
            ],
        ], $overrides);
    }

    public function test_un_administrateur_cree_un_modele_avec_ses_options(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/admin/products', $this->productPayload($category));

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'veste-de-costume-milano')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(2, 'data.colors')
            ->assertJsonPath('data.measurement_fields.0.key', 'chest_cm');

        $product = Product::query()->where('slug', 'veste-de-costume-milano')->firstOrFail();
        $this->assertNotNull($product->published_at);
        $this->assertDatabaseHas('product_options', ['product_id' => $product->id, 'type' => 'color', 'value' => 'bleu-nuit']);
    }

    public function test_un_conseiller_ne_peut_pas_creer_de_modele(): void
    {
        $this->actingAsRole(UserRole::Advisor);
        $category = Category::factory()->create();

        $this->postJson('/api/v1/admin/products', $this->productPayload($category))
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_la_mise_a_jour_desactive_les_options_retirees_sans_les_supprimer(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $category = Category::factory()->create();

        $product = Product::query()->findOrFail(
            $this->postJson('/api/v1/admin/products', $this->productPayload($category))
                ->json('data.id'),
        );

        $payload = $this->productPayload($category, [
            'colors' => [['label' => 'Noir', 'value' => 'noir', 'price_delta' => 0]],
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, $payload)
            ->assertOk()
            ->assertJsonPath('data.colors.0.value', 'noir');

        $this->assertDatabaseHas('product_options', [
            'product_id' => $product->id,
            'value' => 'bleu-nuit',
            'is_active' => false,
        ]);
    }

    public function test_les_donnees_invalides_sont_refusees(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $category = Category::factory()->create();

        $this->postJson('/api/v1/admin/products', $this->productPayload($category, [
            'name' => '',
            'base_price' => 'cent mille',
            'status' => 'en-vente',
        ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name', 'base_price', 'status']]);
    }

    public function test_seul_un_administrateur_peut_gerer_les_categories(): void
    {
        $this->actingAsRole(UserRole::Advisor);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Nouvelle famille'])
            ->assertStatus(403);

        $this->actingAsRole(UserRole::Admin);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Nouvelle famille'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'nouvelle-famille');
    }

    public function test_une_categorie_avec_des_modeles_ne_peut_pas_etre_supprimee(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->deleteJson('/api/v1/admin/categories/'.$category->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 'category_not_empty');
    }

    public function test_une_photo_est_televersee_avec_son_texte_alternatif(): void
    {
        Storage::fake('public');
        $this->actingAsRole(UserRole::Admin);
        $product = Product::factory()->create();

        $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'image' => UploadedFile::fake()->image('costume.jpg', 800, 1000),
            'alt' => 'Costume noir porté en studio',
            'sort_order' => 1,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.alt', 'Costume noir porté en studio');

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'alt' => 'Costume noir porté en studio',
        ]);
    }

    public function test_le_texte_alternatif_d_une_photo_est_obligatoire(): void
    {
        Storage::fake('public');
        $this->actingAsRole(UserRole::Admin);
        $product = Product::factory()->create();

        $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'image' => UploadedFile::fake()->image('costume.jpg'),
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['alt']]);
    }

    public function test_un_modele_supprime_est_archive_et_masque_du_catalogue(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $product = Product::factory()->create(['slug' => 'modele-a-archiver']);

        $this->deleteJson('/api/v1/admin/products/'.$product->id)->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->getJson('/api/v1/products/modele-a-archiver')->assertStatus(404);
    }
}
