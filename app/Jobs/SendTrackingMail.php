<?php

namespace App\Jobs;

use App\Mail\TrackingMail;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envia un correo en segundo plano y actualiza su bitacora (notification_logs).
 *
 * En el hosting, el cron de cPanel ejecuta cada minuto `php artisan schedule:run`,
 * que procesa la cola (ver routes/console.php). Si el SMTP falla, se reintenta.
 */
class SendTrackingMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Segundos de espera entre reintentos. */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $logId,
        public readonly array $to,
        public readonly array $payload,
    ) {
        // Solo se encola si la transaccion que lo origino se confirma.
        $this->afterCommit();
    }

    public function handle(): void
    {
        $log = NotificationLog::find($this->logId);
        $log?->increment('intentos');

        Mail::to($this->to)->send(new TrackingMail($this->payload));

        $log?->forceFill([
            'estado' => NotificationLog::STATUS_SENT,
            'enviado_en' => now(),
            'error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        NotificationLog::whereKey($this->logId)->update([
            'estado' => NotificationLog::STATUS_FAILED,
            'error' => Str::limit($exception->getMessage(), 1000),
        ]);
    }
}
