<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();

        View::composer('layouts.app', function ($view): void {
            $pendingMigrations = 0;
            $user = auth()->user();

            if (! $user instanceof User || ! $user->isAdmin()) {
                $view->with('pendingMigrations', $pendingMigrations);
                return;
            }

            // Sin Schema::hasTable (en MySQL es otra consulta a information_schema
            // en cada pagina del Admin): si la tabla no existe, la consulta falla.
            try {
                $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
                    ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME));
                $ranMigrations = DB::table('migrations')->pluck('migration');
                $pendingMigrations = $migrationFiles->diff($ranMigrations)->count();
            } catch (Throwable) {
                $pendingMigrations = 0;
            }

            $view->with('pendingMigrations', $pendingMigrations);
        });
    }

    /**
     * Permisos por modulo. Los permisos por registro (embarques y clientes)
     * estan en App\Policies; los de campo en App\Support\ShipmentFieldAccess.
     */
    private function registerGates(): void
    {
        $roles = fn (array $allowed) => fn (User $user): bool => $user->hasRole($allowed);

        // Costos de proveedores: los registra Operaciones; Customer solo consulta.
        Gate::define('view-provisions', $roles([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_CUSTOMER]));
        Gate::define('manage-provisions', $roles([User::ROLE_ADMIN, User::ROLE_OPERACIONES]));

        // Facturacion al cliente.
        Gate::define('view-invoices', fn (User $user): bool => $user->isInternal());
        Gate::define('manage-invoices', $roles([User::ROLE_ADMIN, User::ROLE_OPERACIONES]));

        Gate::define('view-reports', fn (User $user): bool => $user->isInternal());

        // Actualizacion masiva por nave/viaje. Cada RO se vuelve a revisar con
        // sus permisos de siempre (ETD/ETA: Operaciones y Admin; hitos: + Customer).
        Gate::define('bulk-update-shipments', $roles([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_CUSTOMER]));

        // Dashboard gerencial y reportes financieros consolidados: Administrador / Gerente.
        Gate::define('view-management', fn (User $user): bool => $user->isAdmin());
        // Excel financiero: Admin (todo) y Comercial (solo sus RO, se filtra en el controlador).
        Gate::define('export-financial', $roles([User::ROLE_ADMIN, User::ROLE_COMERCIAL]));

        // Catalogos financieros.
        Gate::define('manage-suppliers', $roles([User::ROLE_ADMIN, User::ROLE_OPERACIONES]));
        Gate::define('manage-rate-concepts', fn (User $user): bool => $user->isAdmin());

        // Eliminaciones de registros financieros: solo el Administrador.
        Gate::define('delete-records', fn (User $user): bool => $user->isAdmin());
    }
}
