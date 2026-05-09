<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndexIfMissing('shipments', 'shipments_client_fecha_etd_index', ['client_id', 'fecha_etd']);
        $this->createIndexIfMissing('shipments', 'shipments_fecha_etd_index', ['fecha_etd']);
        $this->createIndexIfMissing('shipments', 'shipments_client_fecha_eta_index', ['client_id', 'fecha_eta']);
        $this->createIndexIfMissing('shipments', 'shipments_estado_actual_fecha_eta_index', ['estado_actual', 'fecha_eta']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('shipments', 'shipments_estado_actual_fecha_eta_index');
        $this->dropIndexIfExists('shipments', 'shipments_client_fecha_eta_index');
        $this->dropIndexIfExists('shipments', 'shipments_fecha_etd_index');
        $this->dropIndexIfExists('shipments', 'shipments_client_fecha_etd_index');
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function ($tableBlueprint) use ($columns, $indexName) {
            $tableBlueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function ($tableBlueprint) use ($indexName) {
            $tableBlueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => $index->name === $indexName);
        }

        return DB::table('information_schema.statistics')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
