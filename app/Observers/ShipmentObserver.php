<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Services\ShipmentDateChangeService;
use App\Services\ShipmentNotifier;

/**
 * Reacciona a los cambios del embarque despues de guardarlos.
 * Hace el papel del "trigger" de BD, pero en PHP: sabe quien hizo el cambio,
 * cual era el valor anterior y puede enviar correos.
 */
class ShipmentObserver
{
    public function __construct(
        private readonly ShipmentDateChangeService $dateChanges,
        private readonly ShipmentNotifier $notifier,
    ) {
    }

    public function created(Shipment $shipment): void
    {
        $this->dateChanges->track($shipment, true, auth()->id());

        // Evento A: RO registrada -> Customer asignado + cliente.
        $this->notifier->roRegistered($shipment);
    }

    public function updated(Shipment $shipment): void
    {
        // Evento B1 (dentro del servicio): cambio de ETD/ETA por validar.
        $this->dateChanges->track($shipment, false, auth()->id());

        // Evento C: primera vez que se registra la tarja o el ingreso a almacen.
        if ($this->tarjaWasJustRegistered($shipment)) {
            $this->notifier->cargoArrived($shipment);
        }
    }

    private function tarjaWasJustRegistered(Shipment $shipment): bool
    {
        $hadTarja = $shipment->getOriginal('fecha_tarja') !== null
            || $shipment->getOriginal('fecha_ingreso_almacen') !== null;

        return ! $hadTarja
            && ($shipment->wasChanged('fecha_tarja') || $shipment->wasChanged('fecha_ingreso_almacen'))
            && $shipment->hasTarja();
    }
}
