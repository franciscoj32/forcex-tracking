<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal();
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isInternal();
    }

    /** El Comercial registra clientes (quedan pendientes); el Admin tambien. */
    public function create(User $user): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_COMERCIAL]);
    }

    /**
     * El Admin edita cualquier ficha. El Comercial solo corrige las que el
     * registro y todavia no fueron aprobadas (pendientes o rechazadas).
     */
    public function update(User $user, Client $client): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isComercial()
            && $client->registrado_por_id === $user->id
            && ! $client->isApproved();
    }

    /** Validar datos fiscales y credito, y aprobar o rechazar. */
    public function approve(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }

    /** Contactos: los mantiene quien habla con el cliente o lo vendio. */
    public function manageContacts(User $user, Client $client): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_COMERCIAL, User::ROLE_CUSTOMER]);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
