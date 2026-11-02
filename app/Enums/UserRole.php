<?php

namespace App\Enums;

/**
 * Rôles internes du cahier des charges.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Advisor = 'advisor';
    case Atelier = 'atelier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Advisor => 'Conseiller boutique',
            self::Atelier => 'Atelier / couturier',
        };
    }

    /**
     * Rôles habilités à gérer catalogue, comptes et catégories.
     *
     * @return list<self>
     */
    public static function catalogueManagers(): array
    {
        return [self::Admin];
    }

    /**
     * Rôles habilités à lire les mesures corporelles.
     *
     * @return list<self>
     */
    public static function measurementReaders(): array
    {
        return [self::Admin, self::Advisor, self::Atelier];
    }
}
