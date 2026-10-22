<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plazos de la RO para el modulo de alertas criticas:
 * - ETA original: linea base para medir el retraso de la ETA.
 * - Cut-off cumplido: Operaciones confirma que entrego antes del cierre.
 * - Dias libres pactados en el booking (demurrage, detention) y en el almacen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->date('fecha_eta_original')->nullable()->after('fecha_eta');
            $table->dateTime('cut_off_cumplido_en')->nullable()->after('fecha_cut_off');
            $table->foreignId('cut_off_cumplido_por_id')->nullable()->after('cut_off_cumplido_en')
                ->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('dias_libres_demurrage')->nullable()->after('fecha_descarga');
            $table->unsignedSmallInteger('dias_libres_detention')->nullable()->after('dias_libres_demurrage');
            $table->unsignedSmallInteger('dias_libres_almacen')->nullable()->after('fecha_ingreso_almacen');
        });

        // Linea base de las RO existentes: la primera ETA del historial; si no hay, la actual.
        DB::table('shipments')
            ->whereNotNull('fecha_eta')
            ->select(['id', 'fecha_eta'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $first = DB::table('shipment_date_changes')
                        ->where('shipment_id', $row->id)
                        ->where('campo', 'fecha_eta')
                        ->whereNotNull('valor_nuevo')
                        ->orderBy('id')
                        ->value('valor_nuevo');

                    DB::table('shipments')
                        ->where('id', $row->id)
                        ->update(['fecha_eta_original' => $first ?? $row->fecha_eta]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cut_off_cumplido_por_id');
            $table->dropColumn([
                'fecha_eta_original',
                'cut_off_cumplido_en',
                'dias_libres_demurrage',
                'dias_libres_detention',
                'dias_libres_almacen',
            ]);
        });
    }
};
