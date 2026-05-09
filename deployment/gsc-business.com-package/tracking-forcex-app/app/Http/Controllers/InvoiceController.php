<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]), 403);

        $search = trim((string) $request->string('search'));

        return view('invoices.index', [
            'search' => $search,
            'shipments' => Shipment::query()
                ->with([
                    'client',
                    'invoices' => fn ($query) => $query->latest(),
                ])
                ->withCount('invoices')
                ->withSum('invoices', 'monto')
                ->withMax('invoices', 'created_at')
                ->whereHas('invoices')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($nestedQuery) use ($search) {
                        $nestedQuery
                            ->where('nro_orden_trabajo', 'like', "%{$search}%")
                            ->orWhereHas('invoices', function ($invoiceQuery) use ($search) {
                                $invoiceQuery->where('numero', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderByDesc('invoices_max_created_at')
                ->paginate(8)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        return view('invoices.create', [
            'shipments' => Shipment::with('client')->orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        Invoice::create($this->validatedData($request));

        return redirect()->route('facturacion.index')->with('status', 'Factura registrada correctamente.');
    }

    public function show(Invoice $invoice): RedirectResponse
    {
        return redirect()->route('facturacion.edit', $invoice);
    }

    public function edit(Invoice $invoice): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        return view('invoices.edit', [
            'invoice' => $invoice,
            'shipments' => Shipment::with('client')->orderByDesc('created_at')->get(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        $invoice->update($this->validatedData($request, $invoice));

        return redirect()->route('facturacion.index')->with('status', 'Factura actualizada correctamente.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($invoice->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
            Storage::disk('private')->delete($invoice->archivo_pdf);
        }

        $invoice->delete();

        return redirect()->route('facturacion.index')->with('status', 'Factura eliminada correctamente.');
    }

    private function validatedData(Request $request, ?Invoice $invoice = null): array
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'exists:shipments,id'],
            'numero' => ['required', 'string', 'max:100', Rule::unique('invoices', 'numero')->ignore($invoice)],
            'monto' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:pendiente,emitida,pagada'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        if ($request->hasFile('archivo_pdf')) {
            if ($invoice?->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
                Storage::disk('private')->delete($invoice->archivo_pdf);
            }

            $data['archivo_pdf'] = Storage::disk('private')->putFileAs(
                'facturas/'.now()->format('Y'),
                $request->file('archivo_pdf'),
                now()->format('YmdHis').'_'.Str::random(8).'.pdf'
            );
        } else {
            unset($data['archivo_pdf']);
        }

        return $data;
    }
}
