<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->createCheckIfMissing('shipments_peso_non_negative', 'ALTER TABLE shipments ADD CONSTRAINT shipments_peso_non_negative CHECK (peso IS NULL OR peso >= 0)');
        $this->createCheckIfMissing('shipments_volumen_non_negative', 'ALTER TABLE shipments ADD CONSTRAINT shipments_volumen_non_negative CHECK (volumen IS NULL OR volumen >= 0)');
        $this->createCheckIfMissing('invoices_monto_non_negative', 'ALTER TABLE invoices ADD CONSTRAINT invoices_monto_non_negative CHECK (monto >= 0)');
        $this->createCheckIfMissing('provisions_monto_non_negative', 'ALTER TABLE provisions ADD CONSTRAINT provisions_monto_non_negative CHECK (monto >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropCheckIfExists('provisions', 'provisions_monto_non_negative');
        $this->dropCheckIfExists('invoices', 'invoices_monto_non_negative');
        $this->dropCheckIfExists('shipments', 'shipments_volumen_non_negative');
        $this->dropCheckIfExists('shipments', 'shipments_peso_non_negative');
    }

    private function createCheckIfMissing(string $constraintName, string $statement): void
    {
        if ($this->checkExists($constraintName)) {
            return;
        }

        DB::statement($statement);
    }

    private function dropCheckIfExists(string $table, string $constraintName): void
    {
        if (! $this->checkExists($constraintName)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CHECK {$constraintName}");
    }

    private function checkExists(string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->exists();
    }
};
