<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('tracking_code', 20)->nullable()->unique()->after('nro_orden_trabajo');
        });

        DB::table('shipments')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $shipment): void {
                DB::table('shipments')
                    ->where('id', $shipment->id)
                    ->update(['tracking_code' => $this->generateTrackingCode()]);
            });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn('tracking_code');
        });
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = 'FX-'.Str::upper(Str::random(8));
        } while (DB::table('shipments')->where('tracking_code', $code)->exists());

        return $code;
    }
};
