<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_city',
        'customer_note',
        'status',
        'total_estimate',
        'currency',
        'channel',
        'consent_given',
        'source_ip_hash',
        'confirmed_at',
        'cancelled_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'source_ip_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->reference)) {
                do {
                    $reference = static::newReference();
                } while (static::where('reference', $reference)->exists());

                $order->reference = $reference;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'channel' => OrderChannel::class,
            'total_estimate' => 'integer',
            'consent_given' => 'boolean',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('created_at');
    }

    public function scopeWithStatus(Builder $query, ?OrderStatus $status): Builder
    {
        return $query->when($status !== null, fn (Builder $q) => $q->where('status', $status));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(reference) LIKE ?', [$like])
                ->orWhereRaw('LOWER(customer_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(customer_phone) LIKE ?', [$like]);
        });
    }

    /**
     * Référence publique lisible : STV-2026-00A31.
     * L'alphabet exclut les caractères ambigus (0/O, 1/I).
     */
    public static function newReference(?string $prefix = null): string
    {
        $prefix = $prefix ?? (string) config('catalog.orders.prefix', 'STV');
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';

        for ($i = 0; $i < 5; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return sprintf('%s-%s-%s', Str::upper($prefix), now()->format('Y'), $suffix);
    }

    public function formattedTotal(): string
    {
        return number_format($this->total_estimate, 0, ',', ' ').' '.config('catalog.currency.label');
    }

    /**
     * Nombre d'articles (somme des quantités).
     */
    public function itemsCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
