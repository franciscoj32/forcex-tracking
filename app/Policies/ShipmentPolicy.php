<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Support\ShipmentFieldAccess;

/**
 * Permisos por accion sobre embarques. Los permisos por campo estan en
 * App\Support\ShipmentFieldAccess.
 */
class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal() || $user->role === User::ROLE_CLIENTE;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->role === User::ROLE_CLIENTE) {
            return $shipment->client_id === $user->client_id;
        }

        return $user->isInternal();
    }

    /** Solo el Comercial (y el Admin) registran la RO. */
    public function create(User $user): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_COMERCIAL]);
    }

    /** Puede abrir la edicion si tiene al menos un campo editable. */
    public function update(User $user, Shipment $shipment): bool
    {
        return ShipmentFieldAccess::editableOnUpdate($user, $shipment) !== [];
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }

    /** Registrar hitos de tracking. */
    public function registerTracking(User $user, Shipment $shipment): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_CUSTOMER]);
    }

    /**
     * Ver tarifas de venta: Admin, Comercial y Customer.
     * Operaciones registra costos pero no ve lo que se cobra al cliente.
     */
    public function viewRates(User $user, Shipment $shipment): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_COMERCIAL, User::ROLE_CUSTOMER]);
    }

    /** Ver el margen: el Admin, o el Comercial duenio de la RO. */
    public function viewMargin(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin()
            || ($user->isComercial() && $shipment->comercial_id === $user->id);
    }

    /** Contenedores: datos tecnicos de Operaciones. */
    public function manageContainers(User $user, Shipment $shipment): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]) && ! $shipment->isAirCargo();
    }

    /**
     * Confirmar que se entrego antes del cut-off: quien es duenio de esa
     * fecha (Operaciones o Admin) y solo mientras la carga no zarpa.
     */
    public function confirmCutOff(User $user, Shipment $shipment): bool
    {
        return $shipment->canConfirmCutOff()
            && \App\Support\ShipmentFieldAccess::canEdit($user, $shipment, 'fecha_cut_off');
    }

    /**
     * Validar cambios de ETD/ETA antes de publicarlos al cliente:
     * el Customer asignado a la RO, o el Administrador.
     */
    public function reviewDateChanges(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin()
            || ($user->isCustomer() && $shipment->customer_id === $user->id);
    }

    /**
     * Escribir notas para el cliente (tracking publico y correo).
     * El Customer asignado es el unico punto de contacto; el Admin lo respalda.
     */
    public function communicateWithClient(User $user, Shipment $shipment): bool
    {
        return $this->reviewDateChanges($user, $shipment);
    }

    /**
     * Enviar o retener la Pre-alerta de llegada a los externos (cliente,
     * agencia de aduana, transportista): el Customer asignado, o el Admin.
     * El Customer decide si la retiene, por ejemplo por facturas por pagar.
     */
    public function sendPreAlert(User $user, Shipment $shipment): bool
    {
        return $this->reviewDateChanges($user, $shipment);
    }

    /**
     * Validar los documentos que envia el cliente (Invoice, Packing List,
     * borrador de BL): el Customer asignado, o el Administrador.
     */
    public function validateDocuments(User $user, Shipment $shipment): bool
    {
        return $this->reviewDateChanges($user, $shipment);
    }

    /** Subir y eliminar documentos del embarque. */
    public function manageDocuments(User $user, Shipment $shipment): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_CUSTOMER]);
    }
}
