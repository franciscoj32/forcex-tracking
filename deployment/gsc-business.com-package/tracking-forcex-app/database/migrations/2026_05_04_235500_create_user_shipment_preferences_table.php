<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_shipment_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_pinned')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'shipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shipment_preferences');
    }
};
