<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('assignment_type', ['responsable', 'seguimiento'])->default('seguimiento');
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamps();

            $table->unique(['shipment_id', 'user_id']);
            $table->index(['user_id', 'assignment_type']);
            $table->index(['shipment_id', 'assignment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_user_assignments');
    }
};
