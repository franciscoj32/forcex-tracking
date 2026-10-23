<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Deja listas las dos cuentas principales:
 *
 *  - Desarrollo: el usuario indicado (por defecto "admin") pasa al rol oculto
 *    de Desarrollo. Conserva su contrasena.
 *  - Gerente (Administrador): se crea si no existe, con una contrasena
 *    temporal que se muestra una sola vez. Si ya existe, solo se asegura
 *    que tenga el rol y este activa (la contrasena no cambia, salvo --nueva-clave).
 *
 * Se puede correr varias veces sin duplicar nada:
 *   php artisan forcex:usuarios-base --gerente-usuario=dilcia-estela \
 *       --gerente-nombre="Dilcia Estela" --gerente-correo=destela@empresa.com
 */
class SetupBaseUsers extends Command
{
    protected $signature = 'forcex:usuarios-base
        {--desarrollo=admin : Usuario que pasa a ser la cuenta oculta de Desarrollo}
        {--gerente-usuario= : Usuario de la Gerente}
        {--gerente-nombre= : Nombre visible de la Gerente}
        {--gerente-correo= : Correo de la Gerente}
        {--nueva-clave : Genera una contrasena temporal nueva aunque la Gerente ya exista}';

    protected $description = 'Convierte el usuario tecnico en Desarrollo y crea o actualiza la cuenta de la Gerente';

    public function handle(): int
    {
        $this->setupDeveloper((string) $this->option('desarrollo'));

        $username = trim((string) $this->option('gerente-usuario'));

        if ($username === '') {
            $this->line('Sin --gerente-usuario: no se crea la cuenta de la Gerente.');

            return self::SUCCESS;
        }

        return $this->setupManager($username) ? self::SUCCESS : self::FAILURE;
    }

    private function setupDeveloper(string $username): void
    {
        $developer = User::where('username', $username)->first();

        if (! $developer) {
            $this->warn("No existe el usuario \"{$username}\": no se asignó la cuenta de Desarrollo.");

            return;
        }

        if ($developer->isDeveloper()) {
            $this->info("\"{$username}\" ya es la cuenta de Desarrollo.");

            return;
        }

        $developer->forceFill(['role' => User::ROLE_DEVELOPER, 'estado' => User::ESTADO_ACTIVO])->save();
        $this->info("\"{$username}\" ahora es la cuenta de Desarrollo (oculta para los demás usuarios).");
    }

    private function setupManager(string $username): bool
    {
        $name = trim((string) $this->option('gerente-nombre'));
        $email = mb_strtolower(trim((string) $this->option('gerente-correo')));
        $manager = User::where('username', $username)->first();

        if (! $manager) {
            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Para crear a la Gerente indica --gerente-nombre y un --gerente-correo válido.');

                return false;
            }

            if (User::where('email', $email)->exists()) {
                $this->error("Ya hay otro usuario con el correo {$email}.");

                return false;
            }

            $password = $this->temporaryPassword();

            User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'estado' => User::ESTADO_ACTIVO,
                'email_verified_at' => now(),
            ]);

            $this->info("Cuenta de la Gerente creada: {$username} ({$email}).");
            $this->showPassword($password);

            return true;
        }

        $changes = ['role' => User::ROLE_ADMIN, 'estado' => User::ESTADO_ACTIVO];
        $changes += array_filter(['name' => $name, 'email' => $email]);
        $password = $this->option('nueva-clave') ? $this->temporaryPassword() : null;

        if ($password) {
            $changes['password'] = $password;
        }

        $manager->forceFill($changes)->save();
        $this->info("La cuenta \"{$username}\" ya existía: queda como Administrador / Gerente y activa.");

        if ($password) {
            $this->showPassword($password);
        }

        return true;
    }

    private function temporaryPassword(): string
    {
        // Sin simbolos para que se pueda dictar o escribir sin confusiones.
        return Str::password(12, symbols: false);
    }

    private function showPassword(string $password): void
    {
        $this->newLine();
        $this->line("  Contraseña temporal: <options=bold>{$password}</>");
        $this->line('  Se muestra solo esta vez. Pídele que la cambie en "Mi perfil" al primer ingreso.');
        $this->newLine();
    }
}
