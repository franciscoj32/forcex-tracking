<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /** Los documentos se ven en la pestana Documentos de cada embarque. */
    public function overview(): RedirectResponse
    {
        return redirect()->route('embarques.index');
    }

    public function index(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('view', $shipment);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'documentos',
        ]);
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('manageDocuments', $shipment);

        $data = $request->validate([
            'categoria' => ['required', Rule::in(Document::CATEGORIES)],
            'tipo_documento' => ['required', 'string', 'max:100'],
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $file = $data['archivo'];
        $folder = 'embarques/'.now()->format('Y').'/'.Str::slug($shipment->nro_orden_trabajo);
        $fileName = now()->format('YmdHis').'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
        $path = Storage::disk('private')->putFileAs($folder, $file, $fileName);

        Document::create([
            'shipment_id' => $shipment->id,
            'categoria' => $data['categoria'],
            'tipo_documento' => $data['tipo_documento'],
            'nombre_original' => $file->getClientOriginalName(),
            'nombre_archivo' => $fileName,
            'ruta' => $path,
            'peso' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'documentos',
        ])->with('status', 'Documento cargado correctamente.');
    }

    public function download(Document $document): StreamedResponse
    {
        $document->load('shipment');
        Gate::authorize('view', $document->shipment);

        abort_unless(Storage::disk('private')->exists($document->ruta), 404);

        return Storage::disk('private')->download($document->ruta, $document->nombre_original);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $shipment = $document->shipment;
        Gate::authorize('manageDocuments', $shipment);

        if (Storage::disk('private')->exists($document->ruta)) {
            Storage::disk('private')->delete($document->ruta);
        }

        $document->delete();

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'documentos',
        ])->with('status', 'Documento eliminado correctamente.');
    }
}
