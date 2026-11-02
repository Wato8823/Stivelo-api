<?php

namespace App\Enums;

/**
 * Canal d'où provient la demande. Le MVP ne connaît que WhatsApp ;
 * la valeur est prévue pour accueillir le paiement en ligne (P2).
 */
enum OrderChannel: string
{
    case Whatsapp = 'whatsapp';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Online => 'Boutique en ligne',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $channel) => $channel->value, self::cases());
    }
}
