<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 - Roles.
 *
 * - Agrega el rol "customer" (Customer Service) al enum de roles.
 *   El valor interno del administrador se mantiene como "admin"; la etiqueta
 *   visible ("Administrador / Gerente") vive en User::ROLE_LABELS.
 * - Agrega "acceso_portal": el rol "cliente" existe en la BD pero no inicia
 *   sesion en el backoffice. Esta columna deja preparado un portal futuro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'comercial', 'customer', 'operaciones', 'cliente'])
                ->default('cliente')
                ->change();

            $table->boolean('acceso_portal')->default(false)->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('acceso_portal');

            $table->enum('role', ['admin', 'operaciones', 'comercial', 'cliente'])
                ->default('cliente')
                ->change();
        });
    }
};
