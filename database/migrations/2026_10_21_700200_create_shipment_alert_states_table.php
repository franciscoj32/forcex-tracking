<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acciones de las personas sobre las alertas ("vista", "pospuesta").
 * Las alertas no se guardan: se calculan al abrir el Inicio. Aqui solo
 * queda lo que alguien hizo con ellas.
 *
 * La huella es el plazo o dato que origino la alerta: si el cut-off se rola
 * o la ETA cambia, la huella cambia y la alerta vuelve a aparecer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_alert_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('huella', 40);
            $table->enum('accion', ['vista', 'pospuesta']);
            // Nivel en el momento de la accion: si la alerta sube de nivel, reaparece.
            $table->enum('nivel', ['info', 'warning', 'danger']);
            $table->dateTime('pospuesta_hasta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['shipment_id', 'tipo', 'huella']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_alert_states');
    }
};
