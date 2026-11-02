<?php

namespace Database\Seeders;

use App\Enums\ProductOptionType;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catalogue de démonstration : 12 modèles répartis dans les sept familles,
 * avec couleurs, tissus, tailles, guide des mesures et visuels.
 *
 * Le seeder est idempotent : il peut être rejoué sans dupliquer les données.
 */
class CatalogSeeder extends Seeder
{
    /**
     * Mensurations demandées par type de vêtement (en centimètres).
     *
     * @var array<string, list<array{key: string, label: string, hint: string, required?: bool}>>
     */
    private const MEASUREMENT_PROFILES = [
        'costume' => [
            ['key' => 'chest_cm', 'label' => 'Tour de poitrine', 'hint' => 'Mesurez au niveau le plus fort, sans serrer.'],
            ['key' => 'waist_cm', 'label' => 'Tour de taille', 'hint' => 'Au niveau du creux de la taille.'],
            ['key' => 'hip_cm', 'label' => 'Tour de hanches', 'hint' => 'Au niveau le plus fort des fesses.'],
            ['key' => 'shoulder_cm', 'label' => 'Largeur d’épaules', 'hint' => 'D’une extrémité d’épaule à l’autre.'],
            ['key' => 'sleeve_cm', 'label' => 'Longueur de manche', 'hint' => 'De l’épaule au poignet, bras légèrement plié.'],
            ['key' => 'inseam_cm', 'label' => 'Longueur entrejambe', 'hint' => 'Du haut de l’entrejambe à la cheville.', 'required' => false],
            ['key' => 'height_cm', 'label' => 'Taille totale', 'hint' => 'Sans chaussures.'],
        ],
        'chemise' => [
            ['key' => 'chest_cm', 'label' => 'Tour de poitrine', 'hint' => 'Bras le long du corps.'],
            ['key' => 'neck_cm', 'label' => 'Tour de cou', 'hint' => 'Ajoutez un doigt d’aisance.'],
            ['key' => 'sleeve_cm', 'label' => 'Longueur de manche', 'hint' => 'De l’épaule au poignet.'],
            ['key' => 'waist_cm', 'label' => 'Tour de taille', 'hint' => 'Pour une chemise ajustée.', 'required' => false],
        ],
        'pantalon' => [
            ['key' => 'waist_cm', 'label' => 'Tour de taille', 'hint' => 'Sans ceinture.'],
            ['key' => 'hip_cm', 'label' => 'Tour de hanches', 'hint' => 'Au niveau le plus fort.'],
            ['key' => 'inseam_cm', 'label' => 'Longueur entrejambe', 'hint' => 'Du haut de l’entrejambe à la cheville.'],
            ['key' => 'thigh_cm', 'label' => 'Tour de cuisse', 'hint' => 'Juste sous l’entrejambe.', 'required' => false],
        ],
        'ensemble' => [
            ['key' => 'chest_cm', 'label' => 'Tour de poitrine', 'hint' => 'Pour la veste ou le haut.'],
            ['key' => 'waist_cm', 'label' => 'Tour de taille', 'hint' => 'Au creux de la taille.'],
            ['key' => 'hip_cm', 'label' => 'Tour de hanches', 'hint' => 'Pour le bas.'],
            ['key' => 'inseam_cm', 'label' => 'Longueur entrejambe', 'hint' => 'Du haut de l’entrejambe à la cheville.'],
            ['key' => 'sleeve_cm', 'label' => 'Longueur de manche', 'hint' => 'De l’épaule au poignet.'],
        ],
        'tenue' => [
            ['key' => 'chest_cm', 'label' => 'Tour de poitrine', 'hint' => 'Pour la coupe de la veste.'],
            ['key' => 'waist_cm', 'label' => 'Tour de taille', 'hint' => 'Au creux de la taille.'],
            ['key' => 'shoulder_cm', 'label' => 'Largeur d’épaules', 'hint' => 'D’une extrémité d’épaule à l’autre.'],
            ['key' => 'sleeve_cm', 'label' => 'Longueur de manche', 'hint' => 'De l’épaule au poignet.'],
            ['key' => 'height_cm', 'label' => 'Taille totale', 'hint' => 'Sans chaussures.'],
        ],
    ];

    public function run(): void
    {
        $categories = Category::query()->pluck('id', 'slug');

        foreach ($this->products() as $index => $data) {
            $product = Product::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $categories[$data['category']],
                    'name' => $data['name'],
                    'short_description' => $data['short_description'],
                    'description' => $data['description'],
                    'collection_badge' => $data['badge'] ?? null,
                    'base_price' => $data['base_price'],
                    'status' => ProductStatus::Published,
                    'is_featured' => $data['featured'] ?? false,
                    'custom_size_enabled' => true,
                    'sort_order' => $index + 1,
                    'published_at' => now()->subDays(30 - $index),
                ],
            );

            $this->seedColors($product, $data['colors']);
            $this->seedFabrics($product, $data['fabrics']);
            $this->seedSizes($product);
            $this->seedMeasurements($product, $data['measurements']);
            $this->seedMedia($product, $data['media']);
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $colors
     */
    private function seedColors(Product $product, array $colors): void
    {
        foreach ($colors as $position => [$label, $value]) {
            $product->options()->updateOrCreate(
                ['type' => ProductOptionType::Color, 'value' => $value],
                ['label' => $label, 'price_delta' => 0, 'sort_order' => $position + 1, 'is_active' => true],
            );
        }
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int}>  $fabrics
     */
    private function seedFabrics(Product $product, array $fabrics): void
    {
        foreach ($fabrics as $position => [$label, $value, $delta]) {
            $product->options()->updateOrCreate(
                ['type' => ProductOptionType::Fabric, 'value' => $value],
                ['label' => $label, 'price_delta' => $delta, 'sort_order' => $position + 1, 'is_active' => true],
            );
        }
    }

    private function seedSizes(Product $product): void
    {
        foreach (config('catalog.sizes') as $position => $size) {
            $product->options()->updateOrCreate(
                ['type' => ProductOptionType::Size, 'value' => $size],
                ['label' => $size, 'price_delta' => 0, 'sort_order' => $position + 1, 'is_active' => true],
            );
        }

        $product->options()->updateOrCreate(
            ['type' => ProductOptionType::Size, 'value' => 'Sur mesure'],
            ['label' => 'Sur mesure', 'price_delta' => 0, 'sort_order' => 99, 'is_active' => true],
        );
    }

    private function seedMeasurements(Product $product, string $profile): void
    {
        foreach (self::MEASUREMENT_PROFILES[$profile] as $position => $field) {
            $product->measurementFields()->updateOrCreate(
                ['key' => $field['key']],
                [
                    'label' => $field['label'],
                    'hint' => $field['hint'],
                    'is_required' => $field['required'] ?? true,
                    'min_cm' => config('catalog.measurements.min'),
                    'max_cm' => config('catalog.measurements.max'),
                    'sort_order' => $position + 1,
                ],
            );
        }
    }

    /**
     * @param  list<int>  $images
     */
    private function seedMedia(Product $product, array $images): void
    {
        foreach ($images as $position => $image) {
            $key = sprintf('products/placeholder-%d.png', $image);

            $product->media()->updateOrCreate(
                ['storage_key' => $key],
                [
                    'disk' => 'demo',
                    'alt' => $product->name.' — visuel '.($position + 1),
                    'sort_order' => $position + 1,
                    'width' => 600,
                    'height' => 800,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        $short = 'Coupe ajustée, finitions main et prise de mesures offerte en boutique.';

        return [
            [
                'category' => 'costume-veste',
                'name' => 'Costume Signature Deux Pièces',
                'slug' => 'costume-signature-deux-pieces',
                'short_description' => $short,
                'description' => 'Le costume signature de la maison : veste à deux boutons, épaules naturelles et pantalon à pinces. Confectionné en laine froide pour une tenue impeccable toute la journée.',
                'base_price' => 145_000,
                'badge' => 'Signature',
                'featured' => true,
                'media' => [1, 2],
                'colors' => [['Noir', 'noir'], ['Bleu nuit', 'bleu-nuit'], ['Gris anthracite', 'gris-anthracite']],
                'fabrics' => [['Laine froide', 'laine-froide', 15_000], ['Mélange soie', 'melange-soie', 18_000]],
                'measurements' => 'costume',
            ],
            [
                'category' => 'costume-veste',
                'name' => 'Veste de Costume Milano',
                'slug' => 'veste-de-costume-milano',
                'short_description' => 'Veste seule, doublure légère, idéale pour composer un costume.',
                'description' => 'Une veste droite à revers cranté, pensée pour être portée avec le pantalon Atelier. Doublure respirante et poches intérieures renforcées.',
                'base_price' => 89_000,
                'media' => [3],
                'colors' => [['Noir', 'noir'], ['Bleu nuit', 'bleu-nuit'], ['Beige sable', 'beige-sable']],
                'fabrics' => [['Laine froide', 'laine-froide', 15_000], ['Coton égyptien', 'coton-egyptien', 6_000]],
                'measurements' => 'costume',
            ],
            [
                'category' => 'ensemble-moderne',
                'name' => 'Ensemble Moderne Terra',
                'slug' => 'ensemble-moderne-terra',
                'short_description' => 'Chemise et pantalon coordonnés, esprit relaxé.',
                'description' => 'Ensemble deux pièces en lin lavé : chemise à col ouvert et pantalon à taille élastiquée. La pièce idéale pour les cérémonies en journée.',
                'base_price' => 95_000,
                'media' => [4],
                'colors' => [['Terra', 'terra'], ['Blanc cassé', 'blanc-casse'], ['Olive', 'olive']],
                'fabrics' => [['Lin lavé', 'lin-lave', 8_000], ['Coton égyptien', 'coton-egyptien', 6_000]],
                'measurements' => 'ensemble',
            ],
            [
                'category' => 'ensemble-moderne',
                'name' => 'Ensemble Urbain Coton',
                'slug' => 'ensemble-urbain-coton',
                'short_description' => 'Ensemble ville, coupe droite et confortable.',
                'description' => 'Veste légère non doublée et pantalon droit en coton peigné. Un ensemble polyvalent, du bureau au dîner.',
                'base_price' => 72_000,
                'media' => [5],
                'colors' => [['Noir', 'noir'], ['Gris perle', 'gris-perle']],
                'fabrics' => [['Coton égyptien', 'coton-egyptien', 6_000], ['Lin lavé', 'lin-lave', 8_000]],
                'measurements' => 'ensemble',
            ],
            [
                'category' => 'pantalon',
                'name' => 'Pantalon Fluide Atelier',
                'slug' => 'pantalon-fluide-atelier',
                'short_description' => 'Pantalon fluide à pinces, tombé impeccable.',
                'description' => 'Taille haute, deux pinces et jambe légèrement évasée. Le pantalon fluide de l’atelier, disponible en sur-mesure.',
                'base_price' => 45_000,
                'media' => [6],
                'colors' => [['Noir', 'noir'], ['Bleu nuit', 'bleu-nuit'], ['Beige sable', 'beige-sable']],
                'fabrics' => [['Lin lavé', 'lin-lave', 8_000], ['Laine froide', 'laine-froide', 15_000]],
                'measurements' => 'pantalon',
            ],
            [
                'category' => 'pantalon',
                'name' => 'Pantalon Tailleur Nuit',
                'slug' => 'pantalon-tailleur-nuit',
                'short_description' => 'Pantalon tailleur à pli marqué.',
                'description' => 'Un pantalon tailleur structuré, ceinture doublée et ourlet à ajuster. Se porte avec la veste Milano.',
                'base_price' => 52_000,
                'media' => [7],
                'colors' => [['Bleu nuit', 'bleu-nuit'], ['Gris anthracite', 'gris-anthracite']],
                'fabrics' => [['Laine froide', 'laine-froide', 15_000], ['Mélange soie', 'melange-soie', 18_000]],
                'measurements' => 'pantalon',
            ],
            [
                'category' => 'chemise',
                'name' => 'Chemise Coton Oxford',
                'slug' => 'chemise-coton-oxford',
                'short_description' => 'Chemise Oxford, col boutonné, coton épais.',
                'description' => 'La chemise du quotidien : coton Oxford tissé serré, col boutonné et coupe légèrement ajustée. Se porte aussi bien sous une veste qu’ouverte.',
                'base_price' => 32_000,
                'media' => [8],
                'colors' => [['Blanc', 'blanc'], ['Bleu ciel', 'bleu-ciel']],
                'fabrics' => [['Coton égyptien', 'coton-egyptien', 6_000]],
                'measurements' => 'chemise',
            ],
            [
                'category' => 'chemise',
                'name' => 'Chemise Lin Riviera',
                'slug' => 'chemise-lin-riviera',
                'short_description' => 'Lin lavé, col ouvert, esprit vacances.',
                'description' => 'Une chemise ample en lin lavé, col ouvert sans doublure et boutons en corozo. Parfaite pour les journées chaudes.',
                'base_price' => 38_000,
                'featured' => true,
                'media' => [1],
                'colors' => [['Blanc cassé', 'blanc-casse'], ['Olive', 'olive'], ['Terra', 'terra']],
                'fabrics' => [['Lin lavé', 'lin-lave', 8_000]],
                'measurements' => 'chemise',
            ],
            [
                'category' => 'mode-africaine',
                'name' => 'Boubou Cérémonie Wax',
                'slug' => 'boubou-ceremonie-wax',
                'short_description' => 'Boubou ample en wax, broderie col et poignets.',
                'description' => 'Boubou traditionnel revisité : wax premium, broderie ton sur ton au col et aux poignets. Une pièce de cérémonie qui se transmet.',
                'base_price' => 78_000,
                'badge' => 'Édition wax',
                'media' => [2, 4],
                'colors' => [['Indigo', 'indigo'], ['Terra', 'terra'], ['Or', 'or']],
                'fabrics' => [['Wax premium', 'wax-premium', 10_000], ['Bazin riche', 'bazin-riche', 12_000]],
                'measurements' => 'tenue',
            ],
            [
                'category' => 'mode-africaine',
                'name' => 'Ensemble Wax Bogolan',
                'slug' => 'ensemble-wax-bogolan',
                'short_description' => 'Ensemble chemise et pantalon en bogolan.',
                'description' => 'Association de bogolan et de coton pour un ensemble graphique. Coupe droite, finitions contrastées.',
                'base_price' => 68_000,
                'media' => [3, 6],
                'colors' => [['Écru', 'ecru'], ['Noir', 'noir']],
                'fabrics' => [['Wax premium', 'wax-premium', 10_000], ['Bazin riche', 'bazin-riche', 12_000]],
                'measurements' => 'ensemble',
            ],
            [
                'category' => 'tenue-de-ceremonie',
                'name' => 'Smoking Cérémonie Nuit',
                'slug' => 'smoking-ceremonie-nuit',
                'short_description' => 'Smoking à revers satinés et pantalon assorti.',
                'description' => 'Le smoking de la maison : revers satinés, veste cintrée et pantalon à galon. Confection sur mesure recommandée pour les grandes occasions.',
                'base_price' => 165_000,
                'badge' => 'Cérémonie',
                'featured' => true,
                'media' => [5, 7],
                'colors' => [['Noir', 'noir'], ['Bleu nuit', 'bleu-nuit']],
                'fabrics' => [['Laine froide', 'laine-froide', 15_000], ['Mélange soie', 'melange-soie', 18_000]],
                'measurements' => 'tenue',
            ],
            [
                'category' => 'collection-stive-clothing',
                'name' => 'Pièce Signature Stive',
                'slug' => 'piece-signature-stive',
                'short_description' => 'Série limitée, coupe exclusive de la maison.',
                'description' => 'La pièce manifeste de Stive Clothing : drap de laine exclusif, doublure imprimée et boutons gravés. Chaque pièce est numérotée et réalisée à la commande.',
                'base_price' => 120_000,
                'badge' => 'Série limitée',
                'featured' => true,
                'media' => [8, 1],
                'colors' => [['Noir', 'noir'], ['Terra', 'terra']],
                'fabrics' => [['Mélange soie', 'melange-soie', 18_000], ['Laine froide', 'laine-froide', 15_000]],
                'measurements' => 'tenue',
            ],
        ];
    }
}
