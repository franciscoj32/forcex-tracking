<?php

namespace App\Services\Alerts;

use App\Models\AppSetting;

/**
 * Umbrales globales de las alertas (Configuracion, los define el Admin).
 */
final class AlertThresholds
{
    public function __construct(
        public readonly int $preventiveDays = 5,
        public readonly int $warningHours = 48,
        public readonly int $criticalHours = 24,
        public readonly int $escalationHours = 4,
        public readonly int $etaDelayDays = 2,
        // Pre-alerta de llegada: aparece estas horas antes de la ETA.
        public readonly int $preAlertHours = 72,
    ) {}

    public static function fromSettings(?AppSetting $settings = null): self
    {
        $settings ??= AppSetting::current();
        $value = fn (string $key) => (int) ($settings->getAttribute($key) ?? AppSetting::ALERT_DEFAULTS[$key]);

        return new self(
            preventiveDays: $value('alerta_dias_preventiva'),
            warningHours: $value('alerta_horas_advertencia'),
            criticalHours: $value('alerta_horas_critica'),
            escalationHours: $value('alerta_horas_escalamiento'),
            etaDelayDays: $value('alerta_dias_retraso_eta'),
            preAlertHours: $value('alerta_horas_prealerta'),
        );
    }
}
