<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('nro_orden_trabajo')->unique();
            $table->string('routing_order');
            $table->string('bl');
            $table->enum('tipo_carga', ['FCL', 'LCL', 'AEREO']);
            $table->string('origen');
            $table->string('puerto_carga');
            $table->string('puerto_descarga');
            $table->string('destino_final');
            $table->string('naviera')->nullable();
            $table->string('consignatario')->nullable();
            $table->decimal('peso', 12, 2)->nullable();
            $table->decimal('volumen', 12, 2)->nullable();
            $table->date('fecha_etd')->nullable();
            $table->date('fecha_eta')->nullable();
            $table->date('fecha_descarga')->nullable();
            $table->string('estado_actual');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
