<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 - Catalogos financieros.
 *
 * - rate_concepts: conceptos de cobro/costo (flete, THC, aduana...). Se cargan
 *   aqui los conceptos estandar porque son datos de referencia del sistema.
 * - suppliers: proveedores (navieras, agentes, almacenes, transportistas...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        $concepts = [
            ['OCEAN_FREIGHT', 'Flete maritimo (Ocean Freight)'],
            ['AIR_FREIGHT', 'Flete aereo (Air Freight)'],
            ['THC', 'THC (Terminal Handling Charge)'],
            ['GATE_IN_OUT', 'Gate In / Gate Out'],
            ['CUSTOMS', 'Despacho de aduana'],
            ['LOCAL_TRANSPORT', 'Transporte local'],
            ['BL_FEE', 'Emision de BL'],
            ['HANDLING', 'Handling'],
            ['STORAGE', 'Almacenaje'],
        ];

        DB::table('rate_concepts')->insert(collect($concepts)->map(fn (array $concept, int $index) => [
            'codigo' => $concept[0],
            'nombre' => $concept[1],
            'activo' => true,
            'orden' => ($index + 1) * 10,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('ruc', 20)->nullable()->unique();
            $table->enum('tipo', ['naviera', 'aerolinea', 'agente', 'almacen', 'transportista', 'agencia_aduana', 'otro']);
            $table->string('correo')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('rate_concepts');
    }
};
