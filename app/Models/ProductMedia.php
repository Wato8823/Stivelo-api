<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductMedia extends Model
{
    /** @use HasFactory<\Database\Factories\ProductMediaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'disk',
        'storage_key',
        'alt',
        'sort_order',
        'width',
        'height',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * URL publique de l'image (stockage objet en production).
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->storage_key);
    }
}
