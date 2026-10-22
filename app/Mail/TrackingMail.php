<?php

namespace App\Mail;

use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Correo generico del sistema. El contenido lo arma App\Services\ShipmentNotifier
 * segun el evento (A, B, C, alertas...), asi todos los correos comparten marca
 * y formato. El asunto sigue el estandar de App\Support\MailSubject.
 *
 * Payload:
 *   subject, heading, intro, lines (lista de [etiqueta, valor]), items (lista simple),
 *   note, note_label, cta_label, cta_url, reply_to, reply_to_name, footer, thread_id,
 *   attachments (lista de [path, name, mime] del disco privado; pre-alerta a la agencia)
 */
class TrackingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $payload)
    {
    }

    public function envelope(): Envelope
    {
        $replyTo = filled($this->payload['reply_to'] ?? null)
            ? [new Address($this->payload['reply_to'], $this->payload['reply_to_name'] ?? null)]
            : [];

        $fromAddress = config('mail.from.address');

        return new Envelope(
            // El remitente se muestra con el nombre de la empresa configurada.
            from: $fromAddress ? new Address($fromAddress, AppSetting::current()->company_name) : null,
            replyTo: $replyTo,
            subject: $this->payload['subject'],
        );
    }

    /** Agrupa los correos de un mismo embarque en un solo hilo. */
    public function headers(): Headers
    {
        $threadId = $this->payload['thread_id'] ?? null;

        if (blank($threadId)) {
            return new Headers();
        }

        return new Headers(
            references: [$threadId],
            text: ['In-Reply-To' => "<{$threadId}>"],
        );
    }

    /** Documentos de la RO (disco privado). Si un archivo ya no existe, se omite. */
    public function attachments(): array
    {
        return collect($this->payload['attachments'] ?? [])
            ->filter(fn (array $file) => Storage::disk('private')->exists($file['path']))
            ->map(fn (array $file) => Attachment::fromStorageDisk('private', $file['path'])
                ->as($file['name'])
                ->withMime($file['mime'] ?: 'application/octet-stream'))
            ->values()
            ->all();
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tracking',
            with: [
                'payload' => $this->payload,
                'company' => AppSetting::current()->company_name,
            ],
        );
    }
}
