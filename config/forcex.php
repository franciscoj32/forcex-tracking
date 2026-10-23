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

    /*
    | Tasa de IGV de las cotizaciones (solo para las lineas marcadas como afectas).
    */
    'igv_rate' => (float) env('FORCEX_IGV_RATE', 0.18),

    /*
    | Dias que dura el enlace del PDF de una cotizacion enviada por WhatsApp.
    */
    'quotation_link_days' => (int) env('FORCEX_QUOTATION_LINK_DAYS', 30),

    /*
    | Modulo de Productividad: con cuantas horas de anticipacion se avisa en la
    | campanita que una tarea esta por vencer (solo tareas con hora).
    */
    'task_reminder_hours' => (int) env('FORCEX_TASK_REMINDER_HOURS', 2),

];
