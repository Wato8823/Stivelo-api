<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Droits sur les demandes de commande.
 *
 * - admin    : tout, y compris la suppression logique et les mesures ;
 * - advisor  : consulter, changer le statut, lire les mesures ;
 * - atelier  : consulter une demande et ses mesures, sans changer le statut.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isActive();
    }

    public function create(User $user): bool
    {
        return false; // les demandes sont créées par le canal public
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isActive() && $user->hasAnyRole(UserRole::Admin, UserRole::Advisor);
    }

    public function viewMeasurements(User $user, Order $order): bool
    {
        return $user->isActive() && $user->hasAnyRole(...UserRole::measurementReaders());
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isActive() && $user->isAdmin();
    }
}
