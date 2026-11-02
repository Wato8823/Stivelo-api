<?php

namespace App\Models;

use App\Enums\ProductOptionType;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'collection_badge',
        'base_price',
        'status',
        'is_featured',
        'custom_size_enabled',
        'sort_order',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'custom_size_enabled' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order')->orderBy('label');
    }

    public function measurementFields(): HasMany
    {
        return $this->hasMany(ProductMeasurementField::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeForCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace('%', '\%', mb_strtolower($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(short_description) LIKE ?', [$like])
                ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePriceBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('base_price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('base_price', '<=', $max));
    }

    public function optionValues(ProductOptionType $type): array
    {
        return $this->options
            ->filter(fn ($option) => $option->type === $type && $option->is_active)
            ->pluck('value')
            ->all();
    }

    public function formattedPrice(): string
    {
        return number_format($this->base_price, 0, ',', ' ').' '.config('catalog.currency.label');
    }
}
