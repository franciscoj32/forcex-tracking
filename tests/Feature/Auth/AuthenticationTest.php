<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_using_username(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
        ]);

        $response = $this->post('/login', [
            'login' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
            'estado' => User::ESTADO_INACTIVO,
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
    }

    public function test_client_role_users_can_not_authenticate_into_internal_panel(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CLIENTE,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
