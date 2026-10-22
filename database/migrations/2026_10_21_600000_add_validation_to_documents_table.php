<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validacion documental por Customer Service: los documentos que envia el
 * cliente (Commercial Invoice, Packing List, borrador de BL) quedan
 * pendientes hasta que el Customer los aprueba u observa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // null = el tipo de documento no requiere validacion.
            $table->string('estado_validacion', 20)->nullable()->after('tipo_documento');
            $table->text('observacion_validacion')->nullable()->after('estado_validacion');
            $table->foreignId('validado_por_id')->nullable()->after('observacion_validacion')->constrained('users')->nullOnDelete();
            $table->timestamp('validado_en')->nullable()->after('validado_por_id');
            $table->index(['shipment_id', 'estado_validacion']);
        });

        // Los documentos que ya estaban cargados se dan por revisados.
        DB::table('documents')
            ->whereIn('tipo_documento', ['Commercial Invoice', 'Packing List', 'Borrador de BL'])
            ->update(['estado_validacion' => 'aprobado']);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['shipment_id', 'estado_validacion']);
            $table->dropConstrainedForeignId('validado_por_id');
            $table->dropColumn(['estado_validacion', 'observacion_validacion', 'validado_en']);
        });
    }
};
