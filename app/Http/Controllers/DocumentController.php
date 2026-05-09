<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function overview(): View
    {
        $query = Shipment::with(['client', 'documents' => fn ($builder) => $builder->latest()])
            ->whereHas('documents')
            ->latest('updated_at');

        if (auth()->user()->role === User::ROLE_CLIENTE) {
            $query->where('client_id', auth()->user()->client_id);
        }

        return view('documents.overview', [
            'shipments' => $query->paginate(12),
        ]);
    }

    public function index(Shipment $shipment): RedirectResponse
    {
        $this->authorizeAccess($shipment);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'documentos',
        ]);
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

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
        $this->authorizeAccess($document->shipment);

        abort_unless(Storage::disk('private')->exists($document->ruta), 404);

        return Storage::disk('private')->download($document->ruta, $document->nombre_original);
    }

    public function destroy(Document $document): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        $shipment = $document->shipment;

        if (Storage::disk('private')->exists($document->ruta)) {
            Storage::disk('private')->delete($document->ruta);
        }

        $document->delete();

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'documentos',
        ])->with('status', 'Documento eliminado correctamente.');
    }

    private function authorizeAccess(Shipment $shipment): void
    {
        $user = auth()->user();

        if ($user->role === User::ROLE_CLIENTE && $shipment->client_id !== $user->client_id) {
            abort(403);
        }
    }
}
