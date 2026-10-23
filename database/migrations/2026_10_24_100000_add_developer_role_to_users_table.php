<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rol oculto "desarrollo": cuenta tecnica por encima del Administrador / Gerente
 * (marca y modulos). Solo amplia la lista del enum; no cambia ningun usuario.
 * Para asignar las cuentas: php artisan forcex:usuarios-base
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['desarrollo', 'admin', 'comercial', 'customer', 'operaciones', 'cliente'])
                ->default('cliente')
                ->change();
        });
    }

    public function down(): void
    {
        // Antes de quitar el valor, Desarrollo vuelve a ser Administrador.
        DB::table('users')->where('role', 'desarrollo')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'comercial', 'customer', 'operaciones', 'cliente'])
                ->default('cliente')
                ->change();
        });
    }
};
