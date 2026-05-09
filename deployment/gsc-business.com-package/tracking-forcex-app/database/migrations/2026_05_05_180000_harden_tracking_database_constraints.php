<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureNoDuplicateShipmentReferences();

        if ($this->driver() === 'sqlite') {
            $this->rebuildShipmentEventsTableForSqlite();
            $this->rebuildDocumentsTableForSqlite();
        } else {
            $this->makeShipmentEventsUserNullableWithHistory();
            $this->makeDocumentsUserNullableWithHistory();
        }

        $this->createShipmentIndexes();
        $this->createSupportingIndexes();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('users', 'users_role_estado_index');
        $this->dropIndexIfExists('provisions', 'provisions_shipment_fecha_index');
        $this->dropIndexIfExists('invoices', 'invoices_shipment_estado_index');
        $this->dropIndexIfExists('documents', 'documents_shipment_categoria_created_at_index');
        $this->dropIndexIfExists('shipment_events', 'shipment_events_shipment_fecha_index');
        $this->dropIndexIfExists('shipment_events', 'shipment_events_shipment_state_date_unique');
        $this->dropIndexIfExists('shipments', 'shipments_estado_endoso_responsable_index');
        $this->dropIndexIfExists('shipments', 'shipments_estado_actual_updated_at_index');
        $this->dropIndexIfExists('shipments', 'shipments_client_estado_actual_index');
        $this->dropIndexIfExists('shipments', 'shipments_client_updated_at_index');
        $this->dropIndexIfExists('shipments', 'shipments_client_routing_order_unique');
        $this->dropIndexIfExists('shipments', 'shipments_client_bl_unique');
    }

    private function ensureNoDuplicateShipmentReferences(): void
    {
        $duplicateBl = DB::table('shipments')
            ->select('client_id', 'bl')
            ->groupBy('client_id', 'bl')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateBl) {
            throw new RuntimeException('No se puede aplicar la restricción única client_id + bl porque existen BL duplicados por cliente.');
        }

        $duplicateRoutingOrder = DB::table('shipments')
            ->select('client_id', 'routing_order')
            ->groupBy('client_id', 'routing_order')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateRoutingOrder) {
            throw new RuntimeException('No se puede aplicar la restricción única client_id + routing_order porque existen routing orders duplicados por cliente.');
        }
    }

    private function driver(): string
    {
        return DB::getDriverName();
    }

    private function makeShipmentEventsUserNullableWithHistory(): void
    {
        if ($this->foreignKeyDeletesAs('shipment_events', 'user_id') === 'SET NULL' && $this->columnIsNullable('shipment_events', 'user_id')) {
            return;
        }

        DB::statement('ALTER TABLE shipment_events DROP FOREIGN KEY shipment_events_user_id_foreign');
        DB::statement('ALTER TABLE shipment_events MODIFY user_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE shipment_events ADD CONSTRAINT shipment_events_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    private function makeDocumentsUserNullableWithHistory(): void
    {
        if ($this->foreignKeyDeletesAs('documents', 'user_id') === 'SET NULL' && $this->columnIsNullable('documents', 'user_id')) {
            return;
        }

        DB::statement('ALTER TABLE documents DROP FOREIGN KEY documents_user_id_foreign');
        DB::statement('ALTER TABLE documents MODIFY user_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    private function rebuildShipmentEventsTableForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('DROP TABLE IF EXISTS shipment_events_new');
        DB::statement("
            CREATE TABLE shipment_events_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                shipment_id INTEGER NOT NULL,
                estado VARCHAR NOT NULL,
                fecha DATETIME NOT NULL,
                descripcion TEXT NULL,
                user_id INTEGER NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        DB::statement("
            INSERT INTO shipment_events_new (id, shipment_id, estado, fecha, descripcion, user_id, created_at, updated_at)
            SELECT id, shipment_id, estado, fecha, descripcion, user_id, created_at, updated_at
            FROM shipment_events
        ");
        DB::statement('DROP TABLE shipment_events');
        DB::statement('ALTER TABLE shipment_events_new RENAME TO shipment_events');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function rebuildDocumentsTableForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('DROP TABLE IF EXISTS documents_new');
        DB::statement("
            CREATE TABLE documents_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                shipment_id INTEGER NOT NULL,
                categoria VARCHAR NOT NULL DEFAULT 'operativo',
                tipo_documento VARCHAR NOT NULL,
                nombre_original VARCHAR NOT NULL,
                nombre_archivo VARCHAR NOT NULL,
                ruta VARCHAR NOT NULL,
                peso INTEGER NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                user_id INTEGER NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        DB::statement("
            INSERT INTO documents_new (id, shipment_id, categoria, tipo_documento, nombre_original, nombre_archivo, ruta, peso, mime_type, user_id, created_at, updated_at)
            SELECT id, shipment_id, categoria, tipo_documento, nombre_original, nombre_archivo, ruta, peso, mime_type, user_id, created_at, updated_at
            FROM documents
        ");
        DB::statement('DROP TABLE documents');
        DB::statement('ALTER TABLE documents_new RENAME TO documents');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function createShipmentIndexes(): void
    {
        $this->createUniqueIndexIfMissing('shipments', 'shipments_client_bl_unique', ['client_id', 'bl']);
        $this->createUniqueIndexIfMissing('shipments', 'shipments_client_routing_order_unique', ['client_id', 'routing_order']);
        $this->createIndexIfMissing('shipments', 'shipments_client_updated_at_index', ['client_id', 'updated_at']);
        $this->createIndexIfMissing('shipments', 'shipments_client_estado_actual_index', ['client_id', 'estado_actual']);
        $this->createIndexIfMissing('shipments', 'shipments_estado_actual_updated_at_index', ['estado_actual', 'updated_at']);
        $this->createIndexIfMissing('shipments', 'shipments_estado_endoso_responsable_index', ['estado_endoso', 'responsable_endoso_id']);
    }

    private function createSupportingIndexes(): void
    {
        $this->createUniqueIndexIfMissing('shipment_events', 'shipment_events_shipment_state_date_unique', ['shipment_id', 'estado', 'fecha']);
        $this->createIndexIfMissing('shipment_events', 'shipment_events_shipment_fecha_index', ['shipment_id', 'fecha']);
        $this->createIndexIfMissing('documents', 'documents_shipment_categoria_created_at_index', ['shipment_id', 'categoria', 'created_at']);
        $this->createIndexIfMissing('invoices', 'invoices_shipment_estado_index', ['shipment_id', 'estado']);
        $this->createIndexIfMissing('provisions', 'provisions_shipment_fecha_index', ['shipment_id', 'fecha']);
        $this->createIndexIfMissing('users', 'users_role_estado_index', ['role', 'estado']);
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

    private function createUniqueIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function ($tableBlueprint) use ($columns, $indexName) {
            $tableBlueprint->unique($columns, $indexName);
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
        if ($this->driver() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => $index->name === $indexName);
        }

        return DB::table('information_schema.statistics')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    private function foreignKeyDeletesAs(string $table, string $column): ?string
    {
        if ($this->driver() === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");

            return collect($foreignKeys)
                ->firstWhere('from', $column)?->on_delete;
        }

        $record = DB::table('information_schema.referential_constraints as rc')
            ->join('information_schema.key_column_usage as kcu', function ($join) {
                $join->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME')
                    ->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.CONSTRAINT_SCHEMA');
            })
            ->whereRaw('rc.CONSTRAINT_SCHEMA = DATABASE()')
            ->where('kcu.TABLE_NAME', $table)
            ->where('kcu.COLUMN_NAME', $column)
            ->select('rc.DELETE_RULE')
            ->first();

        return $record?->DELETE_RULE;
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        if ($this->driver() === 'sqlite') {
            $columns = DB::select("PRAGMA table_info('{$table}')");

            return collect($columns)
                ->firstWhere('name', $column)?->notnull === 0;
        }

        $record = DB::table('information_schema.columns')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->select('IS_NULLABLE')
            ->first();

        return $record?->IS_NULLABLE === 'YES';
    }
};
