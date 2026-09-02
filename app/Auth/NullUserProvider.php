<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Provider que nunca devuelve un usuario.
 *
 * Esta app corre local, con un solo usuario y sin login, asi que no hay modelo
 * `User` ni tabla `users`. Pero el framework (y Livewire, al autorizar la subida
 * de archivos) resuelve el guard `web` de todos modos, y `SessionGuard` exige un
 * `UserProvider` de verdad: dejarlo en `null` revienta con un TypeError en el
 * endpoint de uploads.
 *
 * Esto hace explicito el contrato: el guard existe y siempre contesta "no hay
 * usuario".
 */
final class NullUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        //
    }
}
