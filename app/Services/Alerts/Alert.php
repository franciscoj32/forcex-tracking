<?php

namespace App\Services\Alerts;

use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Alerta critica calculada al vuelo (no se guarda en base de datos).
 *
 * - Con plazo (cut-off, demurrage, detention, almacenaje): tiene vence_en y
 *   su nivel sale del semaforo.
 * - De evento (ETA vencida, ETA retrasada): sin plazo, rojas al aparecer.
 */
final class Alert
{
    public const CUT_OFF = 'cut_off';
    public const ETA_OVERDUE = 'eta_vencida';
    public const ETA_DELAYED = 'eta_retrasada';
    public const DEMURRAGE = 'demurrage';
    public const DETENTION = 'detention';
    public const STORAGE = 'almacenaje';
    public const PRE_ARRIVAL = 'pre_alerta';

    public const TYPE_LABELS = [
        self::CUT_OFF => 'Cut-off',
        self::ETA_OVERDUE => 'ETA vencida',
        self::ETA_DELAYED => 'ETA retrasada',
        self::DEMURRAGE => 'Demurrage',
        self::DETENTION => 'Detention',
        self::STORAGE => 'Almacenaje',
        self::PRE_ARRIVAL => 'Pre-alerta de llegada',
    ];

    public const STATUS_ACTIVE = 'activa';
    public const STATUS_SEEN = 'vista';
    public const STATUS_SNOOZED = 'pospuesta';

    /** Lo completa AlertBoard con las acciones guardadas. */
    public string $status = self::STATUS_ACTIVE;

    public ?User $seenBy = null;

    public ?CarbonImmutable $seenAt = null;

    public ?CarbonImmutable $snoozedUntil = null;

    /** Accion guardada que la oculta (para "Reactivar"). */
    public ?int $stateId = null;

    public bool $escalated = false;

    private function __construct(
        public readonly Shipment $shipment,
        public readonly string $type,
        public readonly string $level,
        public readonly ?CarbonImmutable $dueAt,
        public readonly CarbonImmutable $criticalSince,
        public readonly string $fingerprint,
        public readonly array $data = [],
    ) {}

    public static function deadline(Shipment $shipment, string $type, CarbonImmutable $dueAt, string $level, CarbonImmutable $criticalSince, array $data = []): self
    {
        return new self($shipment, $type, $level, $dueAt, $criticalSince, $dueAt->format('YmdHi'), $data);
    }

    public static function event(Shipment $shipment, string $type, CarbonImmutable $criticalSince, string $fingerprint, array $data = []): self
    {
        return new self($shipment, $type, AlertLevel::DANGER, null, $criticalSince, $fingerprint, $data);
    }

    /** Identifica la alerta frente a las acciones guardadas (shipment_alert_states). */
    public function key(): string
    {
        return "{$this->shipment->id}|{$this->type}|{$this->fingerprint}";
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function levelLabel(): string
    {
        return AlertLevel::LABELS[$this->level] ?? $this->level;
    }

    public function isEvent(): bool
    {
        return $this->dueAt === null;
    }

    /** Para ordenar: lo que vence antes (o lleva mas tiempo en rojo) primero. */
    public function sortMoment(): CarbonImmutable
    {
        return $this->dueAt ?? $this->criticalSince;
    }

    /** Lo que importa de la alerta, en una linea. */
    public function detail(): string
    {
        $s = $this->shipment;
        $d = $this->data;

        return match ($this->type) {
            self::CUT_OFF => trim(collect([
                $s->puerto_carga && $s->puerto_descarga ? "{$s->puerto_carga} → {$s->puerto_descarga}" : null,
                $s->naviera,
            ])->filter()->join(' · ')) ?: 'Entrega de documentos y carga antes del cierre.',
            self::ETA_OVERDUE => 'ETA vencida hace '.$d['dias_vencida'].' '.($d['dias_vencida'] === 1 ? 'día' : 'días')
                ." ({$d['eta']}) y la carga no registra arribo.",
            self::ETA_DELAYED => "ETA original {$d['original']} → nueva {$d['nueva']} (+{$d['dias']} días)"
                .($s->naviera ? " · {$s->naviera}" : ''),
            self::DEMURRAGE => "Día {$d['dia']} de {$d['libres']} días libres · "
                .($d['total'] === 0
                    ? 'sin contenedores registrados'
                    : "{$d['pendientes']} de {$d['total']} ".($d['total'] === 1 ? 'contenedor' : 'contenedores').' sin retirar'),
            self::DETENTION => count($d['contenedores']).' '.(count($d['contenedores']) === 1 ? 'contenedor' : 'contenedores')
                .' por devolver vacío: '.implode(', ', $d['contenedores']),
            self::STORAGE => trim(($d['almacen'] ? "{$d['almacen']} · " : '')."día {$d['dia']} de {$d['libres']} días libres"),
            self::PRE_ARRIVAL => $this->preArrivalDetail(),
            default => '',
        };
    }

    /** "ETA 24/10 · MSC ANNA 442W · Chancay · Retenida por María: facturas por pagar." */
    private function preArrivalDetail(): string
    {
        $s = $this->shipment;
        $d = $this->data;

        $base = collect([
            "ETA {$d['eta']}",
            $s->nave ? trim($s->nave.' '.($s->viaje ?? '')) : null,
            $s->puerto_descarga,
        ])->filter()->join(' · ');

        $status = match (true) {
            filled($d['retenida'] ?? null) => "Retenida por {$d['retenida_por']}: {$d['retenida']}",
            filled($d['enviada_eta'] ?? null) => "Se envió con ETA {$d['enviada_eta']}; reenvía con la nueva fecha",
            default => 'Falta enviar el aviso al cliente, la agencia de aduana y el transportista',
        };

        return "{$base}. {$status}.";
    }

    /** Texto grande de la derecha: "vence en 18 h", "vencido hace 2 días", "Crítica". */
    public function countdown(CarbonImmutable $now): string
    {
        if ($this->dueAt === null) {
            return $this->levelLabel();
        }

        $minutes = (int) $now->diffInMinutes($this->dueAt, false);
        $span = self::span(abs($minutes));

        // La pre-alerta cuenta hacia la llegada, no hacia un vencimiento.
        if ($this->type === self::PRE_ARRIVAL) {
            return $minutes >= 0 ? "llega en {$span}" : "ETA hace {$span}";
        }

        return $minutes >= 0 ? "vence en {$span}" : "vencido hace {$span}";
    }

    /** Linea pequena bajo la cuenta regresiva: la fecha exacta o desde cuando esta en rojo. */
    public function subLabel(CarbonImmutable $now): string
    {
        if ($this->type === self::PRE_ARRIVAL && $this->dueAt !== null) {
            return 'ETA '.$this->dueAt->format('d/m'); // la ETA es una fecha, sin hora
        }

        if ($this->dueAt !== null) {
            return $this->dueAt->format('d/m H:i');
        }

        return 'desde hace '.$this->criticalFor($now);
    }

    /** Cuanto lleva en rojo (para la etiqueta "Escalada"). */
    public function criticalFor(CarbonImmutable $now): string
    {
        return self::span(max(0, (int) $this->criticalSince->diffInMinutes($now, false)));
    }

    private static function span(int $minutes): string
    {
        $hours = intdiv($minutes, 60);

        return match (true) {
            $hours < 1 => 'menos de 1 h',
            $hours < 48 => "{$hours} h",
            default => intdiv($hours, 24).' días',
        };
    }

    /** Icono (x-nav-icon) de cada tipo. */
    public function icon(): string
    {
        return match ($this->type) {
            self::CUT_OFF => 'hourglass',
            self::ETA_OVERDUE => 'alert',
            self::ETA_DELAYED => 'clock',
            self::DEMURRAGE => 'container',
            self::DETENTION => 'return',
            self::STORAGE => 'warehouse',
            self::PRE_ARRIVAL => 'send',
            default => 'bell',
        };
    }

    /** Resumen de una linea para el correo diario. */
    public function summary(CarbonImmutable $now): string
    {
        $client = $this->shipment->client?->razon_social;

        return "RO {$this->shipment->routing_order}".($client ? " · {$client}" : '')
            .": {$this->typeLabel()} ({$this->countdown($now)}). {$this->detail()}";
    }
}
