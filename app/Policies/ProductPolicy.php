<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Le catalogue est consultable par toute l'équipe, mais seul un
 * administrateur peut le modifier (CDC F-07).
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isActive();
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->canManageCatalogue();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isActive() && $user->canManageCatalogue();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isActive() && $user->canManageCatalogue();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->isActive() && $user->canManageCatalogue();
    }
}
