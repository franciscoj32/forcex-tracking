<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quotation;
use App\Models\RateConcept;
use App\Models\Shipment;
use App\Models\User;
use App\Services\QuotationService;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);

        $user = $request->user();
        $view = (string) $request->query('vista', '');
        $search = trim((string) $request->query('q', ''));
        $canFilterComercial = ! $user->isComercial();
        $comercialId = $canFilterComercial ? (int) $request->query('comercial_id') : 0;

        // El Comercial solo ve sus cotizaciones; Admin y Customer, todas.
        $base = fn (): Builder => Quotation::query()
            ->when($user->isComercial(), fn ($query) => $query->where('comercial_id', $user->id))
            ->when($comercialId > 0, fn ($query) => $query->where('comercial_id', $comercialId))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('numero', 'like', "%{$search}%")
                ->orWhere('ruc', 'like', "%{$search}%")
                ->orWhere('razon_social', 'like', "%{$search}%")));

        $filters = [
            '' => fn (Builder $query) => $query,
            Quotation::STATUS_DRAFT => fn (Builder $query) => $query->where('estado', Quotation::STATUS_DRAFT)->notExpired(),
            Quotation::STATUS_SENT => fn (Builder $query) => $query->where('estado', Quotation::STATUS_SENT)->notExpired(),
            Quotation::STATUS_ACCEPTED => fn (Builder $query) => $query->where('estado', Quotation::STATUS_ACCEPTED),
            Quotation::STATUS_REJECTED => fn (Builder $query) => $query->where('estado', Quotation::STATUS_REJECTED),
            'vencidas' => fn (Builder $query) => $query->expired(),
        ];

        if (! array_key_exists($view, $filters)) {
            $view = '';
        }

        $labels = [
            '' => ['Todas', 'list'],
            Quotation::STATUS_DRAFT => ['Borradores', 'summary'],
            Quotation::STATUS_SENT => ['Enviadas', 'send'],
            Quotation::STATUS_ACCEPTED => ['Aceptadas', 'check-circle'],
            Quotation::STATUS_REJECTED => ['Rechazadas', 'x-circle'],
            'vencidas' => ['Vencidas', 'clock'],
        ];

        $query = array_filter(['q' => $search, 'comercial_id' => $comercialId ?: null]);
        $views = [];
        foreach ($filters as $key => $filter) {
            $views[$key] = [
                'label' => $labels[$key][0],
                'icon' => $labels[$key][1],
                'count' => $filter($base())->count(),
                'url' => route('cotizaciones.index', $query + array_filter(['vista' => $key])),
            ];
        }

        return view('quotations.index', [
            'quotations' => $filters[$view]($base())
                ->with(['comercial:id,name', 'items'])
                ->latest('fecha')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'views' => $views,
            'currentView' => $view,
            'search' => $search,
            'comercialId' => $comercialId,
            'comerciales' => $canFilterComercial
                ? User::query()->whereIn('id', Quotation::query()->select('comercial_id'))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Quotation::class);

        $quotation = $this->quotations->newQuotation();

        // "Cotizar" desde la ficha del cliente: llega con sus datos.
        if ($client = Client::query()->with('contacts')->find($request->integer('client_id'))) {
            $contact = $client->contacts->first();
            $quotation->fill([
                'ruc' => $client->ruc,
                'razon_social' => $client->razon_social,
                'direccion' => $client->direccion,
                'contacto_nombre' => $contact?->nombre,
                'contacto_correo' => $contact?->correo ?? $client->correo,
                'contacto_telefono' => $contact?->telefono ?? $client->telefono,
            ]);
        }

        return view('quotations.create', $this->formData($quotation));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Quotation::class);

        [$data, $items] = $this->validated($request);
        $quotation = $this->quotations->save($this->quotations->newQuotation(), $data, $items, $request->user());

        return redirect()->route('cotizaciones.show', $quotation)
            ->with('status', "Cotización {$quotation->numero} guardada. Ya puedes enviarla por correo o WhatsApp.");
    }

    public function show(Request $request, Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        $quotation->load(['items.concept', 'comercial:id,name,email', 'sentBy:id,name', 'client.contacts']);
        $user = $request->user();
        $canSend = $user->can('send', $quotation);

        // Destinatarios sugeridos: el contacto de la cotizacion y los contactos del cliente.
        $contacts = collect([[
            'nombre' => $quotation->contacto_nombre ?: $quotation->razon_social,
            'correo' => $quotation->contacto_correo,
            'telefono' => $quotation->contacto_telefono,
        ]])->merge(($quotation->client?->contacts ?? collect())->map(fn ($contact) => [
            'nombre' => $contact->nombre,
            'correo' => $contact->correo,
            'telefono' => $contact->telefono,
        ]));

        $emailContacts = $contacts->filter(fn ($contact) => filled($contact['correo']))
            ->unique(fn ($contact) => mb_strtolower($contact['correo']))
            ->values();

        $phoneContacts = $contacts
            ->map(fn ($contact) => $contact + ['phone' => WhatsApp::normalizePhone($contact['telefono'])])
            ->filter(fn ($contact) => $contact['phone'] !== null)
            ->unique('phone')
            ->map(fn ($contact) => [
                'name' => $contact['nombre'],
                'first' => \Illuminate\Support\Str::before(trim($contact['nombre']), ' '),
                'phone' => $contact['phone'],
                'display' => WhatsApp::display($contact['phone']),
            ])
            ->values();

        return view('quotations.show', [
            'quotation' => $quotation,
            'totals' => $quotation->totals(),
            'canSend' => $canSend,
            'emailContacts' => $emailContacts,
            'phoneContacts' => $phoneContacts,
            'whatsappMessage' => $canSend ? $this->quotations->whatsappMessage($quotation, $user, $this->quotations->signedUrl($quotation)) : '',
            'mailEnabled' => $this->quotations->mailEnabled(),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        Gate::authorize('update', $quotation);

        $quotation->load('items');

        return view('quotations.edit', $this->formData($quotation));
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('update', $quotation);

        [$data, $items] = $this->validated($request);
        $this->quotations->save($quotation, $data, $items, $request->user());

        return redirect()->route('cotizaciones.show', $quotation)->with('status', 'Cotización actualizada.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('delete', $quotation);

        Storage::disk('private')->delete('cotizaciones/'.$quotation->numero.'.pdf');
        $quotation->delete();

        return redirect()->route('cotizaciones.index')->with('status', "Cotización {$quotation->numero} eliminada.");
    }

    /** PDF para ver o descargar desde el sistema. */
    public function pdf(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        return $this->quotations->pdf($quotation)->stream($quotation->pdfFileName());
    }

    /** PDF del enlace de WhatsApp: sin iniciar sesion, con firma que vence. */
    public function publicPdf(Quotation $quotation): Response
    {
        return $this->quotations->pdf($quotation)->stream($quotation->pdfFileName());
    }

    public function email(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('send', $quotation);

        $data = $request->validate([
            'correos' => ['nullable', 'array'],
            'correos.*' => ['email', 'max:255'],
            'otros_correos' => ['nullable', 'string', 'max:1000'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ], [], ['otros_correos' => 'otros correos', 'correos.*' => 'correo']);

        $others = collect(preg_split('/[\s,;]+/', (string) ($data['otros_correos'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));
        $invalid = $others->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($invalid->isNotEmpty()) {
            return back()->withInput()->withErrors(['otros_correos' => 'Revisa estos correos: '.$invalid->join(', ').'.'])->with('dialog', 'correo');
        }

        $emails = collect($data['correos'] ?? [])->merge($others)
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return back()->withInput()->withErrors(['correos' => 'Elige al menos un correo.'])->with('dialog', 'correo');
        }

        if (! $this->quotations->mailEnabled()) {
            return back()->withErrors(['correos' => 'Los correos automáticos están desactivados en este servidor. Descarga el PDF y envíalo desde tu correo.']);
        }

        $this->quotations->sendEmail($quotation, $request->user(), $emails, $data['nota'] ?? null);

        return redirect()->route('cotizaciones.show', $quotation)
            ->with('status', 'Cotización enviada por correo a '.implode(', ', $emails).'.');
    }

    /** Abre el WhatsApp del Comercial con el mensaje y el enlace al PDF (sin costo). */
    public function whatsapp(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('send', $quotation);

        $data = $request->validate([
            'telefono' => ['required', 'string', 'max:30'],
            'mensaje' => ['required', 'string', 'max:2000'],
        ], [], ['telefono' => 'celular']);

        $phone = WhatsApp::normalizePhone($data['telefono']);

        if ($phone === null) {
            return back()->withInput()
                ->withErrors(['telefono' => 'Ese número no parece un celular con WhatsApp. Usa 9 dígitos (ej. 987654321) o el formato internacional (+51…).'])
                ->with('dialog', 'whatsapp');
        }

        $this->quotations->markSent($quotation, $request->user(), Quotation::CHANNEL_WHATSAPP);

        return redirect()->away(WhatsApp::url($phone, trim($data['mensaje'])));
    }

    /** El cliente acepto o rechazo (o se corrige a "enviada"). */
    public function status(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('send', $quotation);

        $data = $request->validate([
            'estado' => ['required', Rule::in([Quotation::STATUS_SENT, Quotation::STATUS_ACCEPTED, Quotation::STATUS_REJECTED])],
        ]);

        $this->quotations->changeStatus($quotation, $data['estado']);

        return redirect()->route('cotizaciones.show', $quotation)
            ->with('status', 'Cotización marcada como '.mb_strtolower(Quotation::STATUS_LABELS[$data['estado']]).'.');
    }

    /** Busca el RUC entre los clientes registrados (sin API externa). */
    public function lookupRuc(Request $request): JsonResponse
    {
        Gate::authorize('create', Quotation::class);

        $ruc = preg_replace('/\D+/', '', (string) $request->query('ruc'));

        if (strlen($ruc) !== 11) {
            return response()->json(['found' => false]);
        }

        $client = Client::query()->with('contacts')->where('ruc', $ruc)->first();

        if (! $client) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'client' => [
                'razon_social' => $client->razon_social,
                'direccion' => $client->direccion,
                'correo' => $client->correo,
                'telefono' => $client->telefono,
                'contacts' => $client->contacts->map(fn ($contact) => [
                    'nombre' => $contact->nombre,
                    'cargo' => $contact->cargo,
                    'correo' => $contact->correo,
                    'telefono' => $contact->telefono,
                ])->values(),
            ],
        ]);
    }

    /* --------------------------------------------------------------------- */

    private function formData(Quotation $quotation): array
    {
        return [
            'quotation' => $quotation,
            'rateConcepts' => RateConcept::query()->active()->ordered()->get(['id', 'nombre']),
            'cargoTypes' => Shipment::TIPOS_CARGA,
            'incoterms' => Shipment::INCOTERMS,
            'currencies' => Quotation::CURRENCIES,
            'igvPercent' => rtrim(rtrim(number_format(Quotation::igvRate() * 100, 2), '0'), '.'),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'ruc' => ['required', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_correo' => ['nullable', 'email', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:30'],
            'tipo_carga' => ['required', Rule::in(Shipment::TIPOS_CARGA)],
            'incoterm' => ['nullable', Rule::in(Shipment::INCOTERMS)],
            'origen' => ['nullable', 'string', 'max:255'],
            'puerto_carga' => ['nullable', 'string', 'max:255'],
            'puerto_descarga' => ['nullable', 'string', 'max:255'],
            'destino' => ['nullable', 'string', 'max:255'],
            'mercancia' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'volumen' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'contenedores' => ['nullable', 'string', 'max:255'],
            'naviera' => ['nullable', 'string', 'max:255'],
            'tiempo_transito' => ['nullable', 'string', 'max:60'],
            'frecuencia' => ['nullable', 'string', 'max:60'],
            'dias_libres' => ['nullable', 'integer', 'min:0', 'max:365'],
            'fecha' => ['required', 'date'],
            'validez_dias' => ['required', 'integer', 'min:1', 'max:180'],
            'condiciones' => ['nullable', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.rate_concept_id' => ['nullable', 'integer', Rule::exists('rate_concepts', 'id')],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.moneda' => ['required', Rule::in(Quotation::CURRENCIES)],
            'items.*.afecto_igv' => ['nullable', 'boolean'],
        ], [
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'items.required' => 'Agrega al menos una tarifa.',
        ], [
            'razon_social' => 'razón social',
            'contacto_correo' => 'correo del contacto',
            'contacto_telefono' => 'celular del contacto',
            'validez_dias' => 'días de validez',
            'dias_libres' => 'días libres',
            'items.*.descripcion' => 'descripción de la tarifa',
            'items.*.cantidad' => 'cantidad',
            'items.*.precio_unitario' => 'precio',
            'items.*.moneda' => 'moneda',
        ]);

        $items = collect($data['items'])->map(fn (array $item) => [
            'rate_concept_id' => $item['rate_concept_id'] ?? null,
            'descripcion' => trim($item['descripcion']),
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $item['precio_unitario'],
            'moneda' => $item['moneda'],
            'afecto_igv' => (bool) ($item['afecto_igv'] ?? false),
        ])->values()->all();

        unset($data['items']);

        // Si el RUC ya es cliente, la cotizacion queda vinculada a su ficha.
        $data['client_id'] = Client::query()->where('ruc', $data['ruc'])->value('id');
        $data['contacto_correo'] = filled($data['contacto_correo'] ?? null) ? mb_strtolower(trim($data['contacto_correo'])) : null;

        return [$data, $items];
    }
}
