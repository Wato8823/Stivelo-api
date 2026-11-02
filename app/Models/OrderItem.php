<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_snapshot',
        'unit_price',
        'quantity',
        'line_total',
        'custom_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'custom_size' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /**
     * Résumé lisible d'une ligne, réutilisé dans le message WhatsApp.
     */
    public function summary(): string
    {
        $snapshot = $this->product_snapshot ?? [];
        $parts = [($snapshot['name'] ?? 'Modèle')];

        foreach (['color', 'fabric', 'size'] as $key) {
            if (! empty($snapshot[$key])) {
                $parts[] = $snapshot[$key];
            }
        }

        if ($this->quantity > 1) {
            $parts[] = 'x'.$this->quantity;
        }

        return implode(' · ', $parts);
    }
}
