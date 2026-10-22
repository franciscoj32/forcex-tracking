<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 - Contactos del cliente (1 cliente -> N contactos).
 *
 * En comercio exterior el contacto de operaciones/logistica casi nunca es el
 * mismo que el de facturacion. Cada contacto indica que tipo de avisos recibe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('cargo')->nullable();
            $table->string('correo');
            $table->string('telefono', 50)->nullable();
            $table->enum('area', ['operaciones', 'logistica', 'facturacion', 'comercial', 'gerencia'])->default('logistica');
            $table->boolean('recibe_tracking')->default(true);
            $table->boolean('recibe_facturacion')->default(false);
            $table->boolean('es_principal')->default(false);
            $table->timestamps();

            $table->unique(['client_id', 'correo']);
            $table->index(['client_id', 'recibe_tracking']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
