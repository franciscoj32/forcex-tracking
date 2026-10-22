<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-alerta de llegada: lo que hizo el Customer con el aviso a externos
 * (cliente, agencia de aduana, transportista) antes de la ETA.
 *
 * - Una fila por accion (historial): "enviada" o "retenida" con su motivo.
 *   La ultima fila de la RO es su estado actual.
 * - eta_enviada: la ETA con la que se envio. Si la ETA cambia despues, la
 *   alerta vuelve a aparecer para reenviar con la fecha nueva.
 * - La alerta del Inicio no se guarda: se calcula al vuelo (AlertDetector).
 *
 * Tambien agrega a Configuracion las horas antes de la ETA (72 por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_pre_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('estado', 20);
            $table->date('eta_enviada')->nullable();
            $table->json('destinatarios')->nullable();
            $table->json('documentos')->nullable();
            $table->text('nota')->nullable();
            $table->string('motivo_retencion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'id']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('alerta_horas_prealerta')->default(72);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('alerta_horas_prealerta');
        });

        Schema::dropIfExists('shipment_pre_alerts');
    }
};
