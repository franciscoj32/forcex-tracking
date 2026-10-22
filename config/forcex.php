<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correos automaticos
    |--------------------------------------------------------------------------
    | Interruptor general de las notificaciones (eventos A, B, C, alertas).
    | Los seeders lo apagan para no encolar correos con datos de prueba.
    */
    'notifications' => (bool) env('FORCEX_NOTIFICATIONS', true),

    /*
    | Hora (Lima) en que se envia el resumen diario de alertas criticas.
    | Los umbrales de las alertas (dias y horas) se definen en Configuracion.
    */
    'daily_alerts_at' => env('FORCEX_DAILY_ALERTS_AT', '07:00'),

    /*
    | Codigo de pais que se agrega a los celulares sin codigo (WhatsApp sin costo).
    */
    'whatsapp_country_code' => env('FORCEX_WHATSAPP_COUNTRY_CODE', '51'),

];
