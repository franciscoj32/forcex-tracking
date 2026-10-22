<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Cut-off cumplido": Operaciones confirma que entrego documentos y carga
 * antes del cierre. Con esto la alerta de cut-off se cierra sola.
 */
class ShipmentCutOffController extends Controller
{
    public function store(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('confirmCutOff', $shipment);

        if ($shipment->cut_off_cumplido_en) {
            return $this->back($shipment, 'El cut-off ya estaba marcado como cumplido.');
        }

        $shipment->forceFill([
            'cut_off_cumplido_en' => now(),
            'cut_off_cumplido_por_id' => auth()->id(),
        ])->save();

        ShipmentEvent::recordAudit($shipment, 'Cut-off del '.$shipment->fecha_cut_off->format('d/m/Y H:i').' marcado como cumplido.', auth()->id());

        return $this->back($shipment, 'Cut-off marcado como cumplido.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('confirmCutOff', $shipment);

        if (! $shipment->cut_off_cumplido_en) {
            return $this->back($shipment, 'El cut-off no estaba marcado como cumplido.');
        }

        $shipment->forceFill([
            'cut_off_cumplido_en' => null,
            'cut_off_cumplido_por_id' => null,
        ])->save();

        ShipmentEvent::recordAudit($shipment, 'Se desmarco el cut-off cumplido.', auth()->id());

        return $this->back($shipment, 'Cut-off desmarcado.');
    }

    /** Vuelve a donde se marco: la ficha o el bloque de Alertas del Inicio. */
    private function back(Shipment $shipment, string $status): RedirectResponse
    {
        $target = request('volver') === 'inicio'
            ? route('dashboard').'#alertas'
            : route('embarques.show', $shipment).'#plazos';

        return redirect()->to($target)->with('status', $status);
    }
}
