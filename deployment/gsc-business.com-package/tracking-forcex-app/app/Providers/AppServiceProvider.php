<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
        View::composer('layouts.app', function ($view): void {
            $pendingMigrations = 0;
            $user = auth()->user();

            if (! $user instanceof User || ! $user->isAdmin()) {
                $view->with('pendingMigrations', $pendingMigrations);
                return;
            }

            try {
                $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
                    ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME));

                if (! Schema::hasTable('migrations')) {
                    $pendingMigrations = $migrationFiles->count();
                } else {
                    $ranMigrations = DB::table('migrations')->pluck('migration');
                    $pendingMigrations = $migrationFiles->diff($ranMigrations)->count();
                }
            } catch (Throwable) {
                $pendingMigrations = 0;
            }

            $view->with('pendingMigrations', $pendingMigrations);
        });
    }
}
