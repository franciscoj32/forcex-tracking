<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 - Datos operativos del embarque (duenio: Operaciones).
 *
 * - Cut-off y agente de origen.
 * - "Donde esta ahora": ubicacion fisica de la carga (texto y coordenadas opcionales).
 * - Fechas publicadas al cliente: el ETD/ETA interno cambia al instante, pero el
 *   cliente solo ve la fecha cuando el Customer valida el cambio.
 * - Tarja e ingreso al almacen de direccionamiento.
 *
 * Unidades: "peso" se guarda en KG y "volumen" en CBM (columnas existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dateTime('fecha_cut_off')->nullable()->after('fecha_etd');
            $table->string('agente_origen')->nullable()->after('naviera');

            $table->string('ubicacion_actual')->nullable()->after('estado_actual');
            $table->decimal('ubicacion_latitud', 10, 7)->nullable()->after('ubicacion_actual');
            $table->decimal('ubicacion_longitud', 10, 7)->nullable()->after('ubicacion_latitud');
            $table->timestamp('ubicacion_actualizada_en')->nullable()->after('ubicacion_longitud');

            $table->date('fecha_etd_cliente')->nullable()->after('fecha_eta');
            $table->date('fecha_eta_cliente')->nullable()->after('fecha_etd_cliente');

            $table->string('almacen_direccionamiento')->nullable()->after('fecha_descarga');
            $table->dateTime('fecha_tarja')->nullable()->after('almacen_direccionamiento');
            $table->unsignedInteger('bultos_tarjados')->nullable()->after('fecha_tarja');
            $table->decimal('peso_tarjado_kg', 12, 2)->nullable()->after('bultos_tarjados');
            $table->dateTime('fecha_ingreso_almacen')->nullable()->after('peso_tarjado_kg');
            $table->text('observacion_tarja')->nullable()->after('fecha_ingreso_almacen');

            $table->index('fecha_cut_off');
            $table->index(['estado_actual', 'fecha_eta_cliente']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['fecha_cut_off']);
            $table->dropIndex(['estado_actual', 'fecha_eta_cliente']);

            $table->dropColumn([
                'fecha_cut_off',
                'agente_origen',
                'ubicacion_actual',
                'ubicacion_latitud',
                'ubicacion_longitud',
                'ubicacion_actualizada_en',
                'fecha_etd_cliente',
                'fecha_eta_cliente',
                'almacen_direccionamiento',
                'fecha_tarja',
                'bultos_tarjados',
                'peso_tarjado_kg',
                'fecha_ingreso_almacen',
                'observacion_tarja',
            ]);
        });
    }
};
