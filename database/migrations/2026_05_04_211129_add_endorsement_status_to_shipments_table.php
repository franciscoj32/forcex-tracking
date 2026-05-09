<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('estado_endoso', ['pendiente', 'en_proceso', 'completado'])->default('pendiente')->after('estado_actual');
            $table->date('fecha_endoso')->nullable()->after('estado_endoso');
            $table->foreignId('responsable_endoso_id')->nullable()->after('fecha_endoso')->constrained('users')->nullOnDelete();
            $table->text('observacion_endoso')->nullable()->after('responsable_endoso_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_endoso_id');
            $table->dropColumn(['estado_endoso', 'fecha_endoso', 'observacion_endoso']);
        });
    }
};
