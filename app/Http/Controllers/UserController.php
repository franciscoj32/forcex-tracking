<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()
                ->whereIn('role', User::INTERNAL_ROLES)
                ->latest()
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => $this->internalRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado correctamente.');
    }

    public function show(User $user): View
    {
        $this->ensureManagedUser($user);

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $this->ensureManagedUser($user);

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->internalRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManagedUser($user);

        $data = $this->validatedData($request, $user);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('usuarios.show', $user)->with('status', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureManagedUser($user);
        abort_if(auth()->id() === $user->id, 422, 'No puedes eliminar tu propio usuario.');

        $user->delete();

        return redirect()->route('usuarios.index')->with('status', 'Usuario eliminado correctamente.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'estado' => ['required', 'in:activo,inactivo'],
            // Cumpleanos (modulo de Productividad). Si no se envia, no cambia.
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'client_id' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_CLIENTE),
                'nullable',
                'exists:clients,id',
            ],
            'password' => $user
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8'],
        ]);

        if (($data['role'] ?? null) !== User::ROLE_CLIENTE) {
            $data['client_id'] = null;
        }

        return $data;
    }

    private function internalRoles(): array
    {
        return User::INTERNAL_ROLES;
    }

    /**
     * Los usuarios cliente y la cuenta de Desarrollo no se administran aqui.
     * Desarrollo no aparece en la lista y no se puede abrir, editar ni borrar
     * (su contrasena se cambia desde "Mi perfil").
     */
    private function ensureManagedUser(User $user): void
    {
        abort_if($user->role === User::ROLE_CLIENTE || $user->isDeveloper(), 404);
    }
}
