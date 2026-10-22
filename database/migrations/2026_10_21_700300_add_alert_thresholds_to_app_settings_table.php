<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbrales globales de las alertas criticas (los define el Admin).
 * Por defecto: verde desde 5 dias, amarilla desde 48 h, roja desde 24 h,
 * escalar al Admin tras 4 h en rojo sin atender y ETA critica con 2 dias de retraso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('alerta_dias_preventiva')->default(5);
            $table->unsignedSmallInteger('alerta_horas_advertencia')->default(48);
            $table->unsignedSmallInteger('alerta_horas_critica')->default(24);
            $table->unsignedSmallInteger('alerta_horas_escalamiento')->default(4);
            $table->unsignedSmallInteger('alerta_dias_retraso_eta')->default(2);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'alerta_dias_preventiva',
                'alerta_horas_advertencia',
                'alerta_horas_critica',
                'alerta_horas_escalamiento',
                'alerta_dias_retraso_eta',
            ]);
        });
    }
};
