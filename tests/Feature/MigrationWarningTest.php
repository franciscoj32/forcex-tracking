<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_warning_when_migrations_are_pending(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        DB::table('migrations')
            ->where('migration', '2026_05_04_230000_add_tracking_code_to_shipments_table')
            ->delete();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hay 1 migración(es) pendiente(s).')
            ->assertSee('php artisan migrate');
    }

    public function test_non_admin_does_not_see_migration_warning(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);

        DB::table('migrations')
            ->where('migration', '2026_05_04_230000_add_tracking_code_to_shipments_table')
            ->delete();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('migración(es) pendiente(s)')
            ->assertDontSee('php artisan migrate');
    }
}
