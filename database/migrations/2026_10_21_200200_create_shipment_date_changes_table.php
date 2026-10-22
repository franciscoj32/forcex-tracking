<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 - Cambios de ETD/ETA pendientes de validacion del Customer.
 *
 * Operaciones cambia la fecha interna al instante. Cada cambio queda aqui como
 * "pendiente" hasta que el Customer lo aprueba (se publica al cliente) o lo
 * descarta (falsa alarma). Si Operaciones vuelve a cambiar la misma fecha
 * antes de la revision, el pendiente anterior queda "reemplazado".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_date_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->enum('campo', ['fecha_etd', 'fecha_eta']);
            $table->date('valor_anterior')->nullable();
            $table->date('valor_nuevo')->nullable();
            // Fecha que el cliente veia cuando se registro el cambio.
            $table->date('valor_publicado')->nullable();
            $table->enum('estado', ['pendiente', 'aprobado', 'descartado', 'reemplazado'])->default('pendiente');
            $table->foreignId('cambiado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->text('nota_cliente')->nullable();
            $table->timestamp('notificado_en')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'campo', 'estado']);
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_date_changes');
    }
};
