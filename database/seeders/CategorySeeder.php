<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Les sept familles de produits à présenter dès la mise en ligne (CDC §5).
 */
class CategorySeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, description: string, sort_order: int}>
     */
    public const CATEGORIES = [
        [
            'name' => 'Costume et veste',
            'slug' => 'costume-veste',
            'description' => 'Costumes deux ou trois pièces, vestes de cérémonie et blazers ajustés.',
            'sort_order' => 1,
        ],
        [
            'name' => 'Ensemble moderne',
            'slug' => 'ensemble-moderne',
            'description' => 'Ensembles coordonnés pour le bureau, les voyages et les occasions habillées.',
            'sort_order' => 2,
        ],
        [
            'name' => 'Pantalon',
            'slug' => 'pantalon',
            'description' => 'Pantalons tailleur, fluides ou ajustés, disponibles en sur-mesure.',
            'sort_order' => 3,
        ],
        [
            'name' => 'Chemise',
            'slug' => 'chemise',
            'description' => 'Chemises en coton, lin ou popeline, coupes classiques et contemporaines.',
            'sort_order' => 4,
        ],
        [
            'name' => 'Mode africaine',
            'slug' => 'mode-africaine',
            'description' => 'Wax, bogolan et bazin revisités dans des coupes actuelles.',
            'sort_order' => 5,
        ],
        [
            'name' => 'Tenue de cérémonie',
            'slug' => 'tenue-de-ceremonie',
            'description' => 'Smokings, tenues de mariage et pièces d’apparat.',
            'sort_order' => 6,
        ],
        [
            'name' => 'Collection Stive Clothing',
            'slug' => 'collection-stive-clothing',
            'description' => 'Les pièces signature imaginées par la maison, en séries limitées.',
            'sort_order' => 7,
        ],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true],
            );
        }
    }
}
