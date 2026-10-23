<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Quotation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Numeracion, guardado, PDF y envio de las cotizaciones.
 *
 * - Numero COT-{año}-{0001}: correlativo por año, generado dentro de la
 *   transaccion con bloqueo para que dos comerciales no tomen el mismo.
 * - Correo: el PDF se guarda en el disco privado y va adjunto.
 * - WhatsApp (sin costo): se comparte un enlace firmado al PDF que vence a
 *   los 30 dias; no hace falta iniciar sesion para abrirlo.
 */
class QuotationService
{
    public function __construct(private readonly ShipmentNotifier $notifier)
    {
    }

    /** Cotizacion nueva con los valores por defecto de Configuracion. */
    public function newQuotation(): Quotation
    {
        $settings = AppSetting::current();

        $quotation = new Quotation([
            'fecha' => today(),
            'validez_dias' => $settings->cotizacion_validez_dias ?: 7,
            'condiciones' => $settings->cotizacion_condiciones,
            'tipo_carga' => 'FCL',
        ]);
        $quotation->estado = Quotation::STATUS_DRAFT;

        return $quotation;
    }

    /**
     * Crea o actualiza la cotizacion y reemplaza sus lineas.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function save(Quotation $quotation, array $data, array $items, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $items, $user) {
            $quotation->fill($data);

            if (! $quotation->exists) {
                $quotation->numero = $this->nextNumber($quotation->fecha->year);
                $quotation->comercial_id = $user->id;
                $quotation->estado = Quotation::STATUS_DRAFT;
            }

            $quotation->save();

            $quotation->items()->delete();
            foreach (array_values($items) as $index => $item) {
                $quotation->items()->create($item + ['orden' => $index]);
            }

            return $quotation->load('items');
        });
    }

    /** Siguiente numero del año. Debe llamarse dentro de una transaccion. */
    public function nextNumber(int $year): string
    {
        $prefix = "COT-{$year}-";

        $last = Quotation::query()
            ->where('numero', 'like', $prefix.'%')
            ->orderByDesc('numero')
            ->lockForUpdate()
            ->value('numero');

        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function pdf(Quotation $quotation): DomPdf
    {
        $quotation->loadMissing(['items', 'comercial']);

        return Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'totals' => $quotation->totals(),
            'igvPercent' => rtrim(rtrim(number_format(Quotation::igvRate() * 100, 2), '0'), '.'),
        ])->setPaper('a4');
    }

    /** Guarda el PDF en el disco privado (para adjuntarlo al correo) y devuelve la ruta. */
    public function storePdf(Quotation $quotation): string
    {
        $path = 'cotizaciones/'.$quotation->numero.'.pdf';

        Storage::disk('private')->put($path, $this->pdf($quotation)->output());

        return $path;
    }

    /**
     * Enlace publico y firmado al PDF. Se firma la ruta relativa (signed:relative)
     * para que funcione igual detras del proxy https del hosting.
     */
    public function signedUrl(Quotation $quotation): string
    {
        $relative = URL::temporarySignedRoute(
            'cotizaciones.publica',
            now()->addDays((int) config('forcex.quotation_link_days', 30)),
            ['quotation' => $quotation->id],
            absolute: false,
        );

        return url($relative);
    }

    /** Si los correos automaticos estan apagados, no se puede enviar por correo. */
    public function mailEnabled(): bool
    {
        return $this->notifier->enabled();
    }

    /** @param  array<int, string>  $emails */
    public function sendEmail(Quotation $quotation, User $sender, array $emails, ?string $note): void
    {
        $path = $this->storePdf($quotation);

        $this->notifier->quotationSent($quotation, $sender, $emails, $note, $path);
        $this->markSent($quotation, $sender, Quotation::CHANNEL_EMAIL);
    }

    /** Un borrador pasa a "enviada". Si ya fue respondida, solo se registra el envio. */
    public function markSent(Quotation $quotation, User $sender, string $channel): void
    {
        if ($quotation->isDraft()) {
            $quotation->estado = Quotation::STATUS_SENT;
        }

        $quotation->forceFill([
            'enviada_en' => now(),
            'enviada_por_id' => $sender->id,
            'ultimo_canal' => $channel,
        ])->save();
    }

    /** Aceptada, rechazada o de vuelta a enviada (si se marco por error). */
    public function changeStatus(Quotation $quotation, string $status): void
    {
        $quotation->forceFill([
            'estado' => $status,
            'respondida_en' => in_array($status, [Quotation::STATUS_ACCEPTED, Quotation::STATUS_REJECTED], true) ? now() : null,
        ])->save();
    }

    /** Mensaje sugerido para WhatsApp. {nombre} se reemplaza por el contacto en pantalla. */
    public function whatsappMessage(Quotation $quotation, User $sender, string $url): string
    {
        $company = AppSetting::current()->company_name;
        $route = $quotation->routeLabel();

        return "Hola {nombre}, te saluda ".Str::before($sender->name, ' ')." de {$company}.\n\n"
            ."Te comparto la cotización {$quotation->numero}"
            .' ('.trim($quotation->cargoLabel().($route !== '' ? " {$route}" : '')).').'
            ."\nVálida hasta el {$quotation->validUntil()->format('d/m/Y')}."
            ."\n\nPuedes verla y descargarla aquí (enlace válido por ".(int) config('forcex.quotation_link_days', 30)." días):\n{$url}";
    }
}
