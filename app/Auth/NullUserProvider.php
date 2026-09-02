<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * A provider that never returns a user.
 *
 * This app runs locally, with a single user and no login, so there is no `User`
 * model and no `users` table. But the framework (and Livewire, when authorizing
 * a file upload) resolves the `web` guard anyway, and `SessionGuard` demands a
 * real `UserProvider`: leaving it `null` blows up with a TypeError on the
 * uploads endpoint.
 *
 * This makes the contract explicit: the guard exists and always answers "there
 * is no user".
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
