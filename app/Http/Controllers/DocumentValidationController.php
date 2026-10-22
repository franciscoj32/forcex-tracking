<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ShipmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Validacion documental: el Customer asignado (o el Admin) aprueba u observa
 * los documentos que envia el cliente. Cada decision queda en la bitacora.
 */
class DocumentValidationController extends Controller
{
    public function approve(Request $request, Document $document): RedirectResponse
    {
        $shipment = $this->authorizeValidation($document);

        $document->forceFill([
            'estado_validacion' => Document::VALIDATION_APPROVED,
            'observacion_validacion' => null,
            'validado_por_id' => $request->user()->id,
            'validado_en' => now(),
        ])->save();

        ShipmentEvent::recordAudit($shipment, "Documento aprobado: {$document->tipo_documento} ({$document->nombre_original}).", $request->user()->id);

        return $this->back($shipment, "{$document->tipo_documento} aprobado.");
    }

    public function observe(Request $request, Document $document): RedirectResponse
    {
        $shipment = $this->authorizeValidation($document);

        $data = $request->validate([
            'observacion_validacion' => ['required', 'string', 'max:1000'],
        ], [], ['observacion_validacion' => 'motivo de la observación']);

        $document->forceFill([
            'estado_validacion' => Document::VALIDATION_OBSERVED,
            'observacion_validacion' => $data['observacion_validacion'],
            'validado_por_id' => $request->user()->id,
            'validado_en' => now(),
        ])->save();

        ShipmentEvent::recordAudit($shipment, "Documento observado: {$document->tipo_documento}. Motivo: {$data['observacion_validacion']}", $request->user()->id);

        return $this->back($shipment, "{$document->tipo_documento} observado. Pide al cliente la versión corregida.");
    }

    private function authorizeValidation(Document $document)
    {
        $shipment = $document->shipment;
        Gate::authorize('validateDocuments', $shipment);
        abort_unless($document->needsValidation(), 422, 'Este documento no requiere validación.');

        return $shipment;
    }

    private function back($shipment, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('embarques.show', ['shipment' => $shipment, 'tab' => 'documentos']).'#archivos')
            ->with('status', $message);
    }
}
