<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 - Finanzas del embarque.
 *
 * - shipments.tipo_cambio: PEN por 1 USD, congelado en cada RO para que las
 *   variaciones del dolar no alteren el margen de operaciones pasadas.
 * - shipment_rates: tarifas de venta pactadas por el Comercial (USD/PEN).
 * - provisions: costos de proveedores con factura, operacion bancaria y moneda.
 * - invoices: moneda de la factura emitida al cliente.
 * - app_settings: tipo de cambio de referencia que define el Administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 8, 4)->nullable()->after('credito_forzado');
        });

        Schema::create('shipment_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('rate_concept_id')->constrained('rate_concepts')->restrictOnDelete();
            $table->decimal('monto', 14, 2);
            $table->enum('moneda', ['USD', 'PEN'])->default('USD');
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'moneda']);
        });

        Schema::table('provisions', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('shipment_id')
                ->constrained('suppliers')->nullOnDelete();
            $table->foreignId('rate_concept_id')->nullable()->after('supplier_id')
                ->constrained('rate_concepts')->nullOnDelete();
            $table->enum('moneda', ['USD', 'PEN'])->default('PEN')->after('monto');
            $table->string('nro_factura_proveedor', 50)->nullable()->after('moneda');
            $table->string('nro_operacion_bancaria', 50)->nullable()->after('nro_factura_proveedor');
            $table->date('fecha_pago')->nullable()->after('nro_operacion_bancaria');

            $table->index(['supplier_id', 'estado']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('moneda', ['USD', 'PEN'])->default('PEN')->after('monto');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->decimal('tipo_cambio_referencial', 8, 4)->nullable();
            $table->timestamp('tipo_cambio_actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['tipo_cambio_referencial', 'tipo_cambio_actualizado_en']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('moneda');
        });

        Schema::table('provisions', function (Blueprint $table) {
            $table->dropIndex(['supplier_id', 'estado']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('rate_concept_id');
            $table->dropColumn(['moneda', 'nro_factura_proveedor', 'nro_operacion_bancaria', 'fecha_pago']);
        });

        Schema::dropIfExists('shipment_rates');

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('tipo_cambio');
        });
    }
};
