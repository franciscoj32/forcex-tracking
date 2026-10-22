<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 - Comunicacion con el cliente.
 *
 * shipment_events:
 * - tipo: "estado" (hito del tracking), "auditoria" (bitacora interna) o
 *   "nota_cliente" (mensaje escrito por el Customer para el cliente).
 * - visible_cliente: lo unico que muestra el tracking publico. Por defecto
 *   un evento es interno; asi ningun texto interno se publica por error.
 * - El indice unico pasa a incluir el tipo: un hito y una auditoria pueden
 *   registrarse en el mismo segundo sin chocar.
 *
 * notification_logs: bitacora de cada correo (a quien, cuando, si fallo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->enum('tipo', ['estado', 'auditoria', 'nota_cliente'])->default('auditoria')->after('estado');
            $table->boolean('visible_cliente')->default(false)->after('tipo');
        });

        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropUnique('shipment_events_shipment_state_date_unique');
            $table->unique(['shipment_id', 'estado', 'fecha', 'tipo'], 'shipment_events_shipment_state_date_type_unique');
            $table->index(['shipment_id', 'visible_cliente', 'fecha']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->string('evento', 50);
            $table->string('asunto');
            $table->json('destinatarios');
            $table->string('responder_a')->nullable();
            $table->enum('estado', ['en_cola', 'enviado', 'fallido', 'sin_destinatarios'])->default('en_cola');
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('enviado_en')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'created_at']);
            $table->index(['estado', 'created_at']);
            $table->index(['evento', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');

        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropIndex(['shipment_id', 'visible_cliente', 'fecha']);
            $table->dropUnique('shipment_events_shipment_state_date_type_unique');
            $table->unique(['shipment_id', 'estado', 'fecha'], 'shipment_events_shipment_state_date_unique');
            $table->dropColumn(['tipo', 'visible_cliente']);
        });
    }
};
