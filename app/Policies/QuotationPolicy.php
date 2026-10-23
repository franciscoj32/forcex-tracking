<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

/**
 * Cotizaciones:
 * - Comercial: crea y ve solo las suyas.
 * - Administrador: ve y gestiona todas.
 * - Customer Service: solo consulta.
 * - Operaciones: sin acceso.
 */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_COMERCIAL, User::ROLE_CUSTOMER]);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_CUSTOMER]) || $this->owns($user, $quotation);
    }

    public function create(User $user): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_COMERCIAL]);
    }

    /** Una vez aceptada o rechazada ya no se cambia lo que se cotizo. */
    public function update(User $user, Quotation $quotation): bool
    {
        return ($user->isAdmin() || $this->owns($user, $quotation)) && ! $quotation->isClosed();
    }

    /** El Admin elimina cualquiera; el Comercial solo sus borradores. */
    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->isAdmin() || ($this->owns($user, $quotation) && $quotation->isDraft());
    }

    /** Enviar por correo o WhatsApp y marcar aceptada o rechazada. */
    public function send(User $user, Quotation $quotation): bool
    {
        return $user->isAdmin() || $this->owns($user, $quotation);
    }

    private function owns(User $user, Quotation $quotation): bool
    {
        return $user->isComercial() && (int) $quotation->comercial_id === (int) $user->id;
    }
}
