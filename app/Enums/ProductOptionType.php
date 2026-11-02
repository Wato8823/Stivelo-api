<?php

namespace App\Enums;

/**
 * Type d'option configurable sur un modèle.
 */
enum ProductOptionType: string
{
    case Color = 'color';
    case Fabric = 'fabric';
    case Size = 'size';

    public function label(): string
    {
        return match ($this) {
            self::Color => 'Couleur',
            self::Fabric => 'Tissu',
            self::Size => 'Taille',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
