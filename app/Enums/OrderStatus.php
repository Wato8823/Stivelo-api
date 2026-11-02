<?php

namespace App\Enums;

/**
 * États d'une demande de commande et transitions autorisées.
 */
enum OrderStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'En attente de confirmation',
            self::Confirmed => 'Confirmée',
            self::InProduction => 'En confection',
            self::Ready => 'Prête',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * États atteignables depuis l'état courant.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingConfirmation => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProduction, self::Cancelled],
            self::InProduction => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
