<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 - Contenedores (1 embarque -> N contenedores).
 * Un contenedor fisico equivale a un FCL; la RO puede llevar varios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('numero', 11);
            $table->enum('tipo', ['20GP', '40GP', '40HC', '20RF', '40RF', '20OT', '40OT', '20FR', '40FR']);
            $table->string('precinto', 50)->nullable();
            $table->unsignedInteger('bultos')->nullable();
            $table->decimal('peso_kg', 12, 2)->nullable();
            $table->decimal('volumen_cbm', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'numero']);
            $table->index('numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_containers');
    }
};
