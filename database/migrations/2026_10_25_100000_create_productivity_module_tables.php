<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo opcional de Productividad y Cultura.
 *
 * Las tablas se crean siempre; el modulo se enciende o apaga en Configuracion
 * (app_settings.modulo_productividad, solo la cuenta de Desarrollo). Apagado,
 * no se consulta ninguna de estas tablas. La tabla de embarques no cambia:
 * la relacion con la RO vive en tasks.shipment_id.
 *
 * - tasks: post-its del dia (sin hora) y tareas con vencimiento, propias o asignadas.
 * - announcements: pizarra de oficina (avisos de la Gerencia y cumpleanos).
 * - users.fecha_nacimiento: para el saludo y el aviso de cumpleanos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('modulo_productividad')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->text('descripcion')->nullable();
            // Dia en que aparece en la lista y el calendario.
            $table->date('fecha');
            // Hora limite. Vacio = post-it del dia.
            $table->dateTime('vence_en')->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creado_por_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asignado_a_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completada_en')->nullable();
            $table->foreignId('completada_por_id')->nullable()->constrained('users')->nullOnDelete();
            // Evita repetir el aviso "por vencer".
            $table->timestamp('recordatorio_enviado_en')->nullable();
            $table->timestamps();

            $table->index(['asignado_a_id', 'completada_en', 'fecha']);
            $table->index(['creado_por_id', 'fecha']);
            $table->index(['completada_en', 'vence_en']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->default('aviso');
            $table->string('titulo', 160);
            $table->text('cuerpo')->nullable();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('fecha_evento')->nullable();
            $table->boolean('fijado')->default(false);
            $table->dateTime('publicado_en');
            $table->dateTime('expira_en')->nullable();
            $table->timestamps();

            $table->index(['publicado_en', 'expira_en']);
            // Un solo saludo por persona y dia aunque la tarea corra dos veces.
            $table->unique(['tipo', 'usuario_id', 'fecha_evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('tasks');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fecha_nacimiento');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('modulo_productividad');
        });
    }
};
