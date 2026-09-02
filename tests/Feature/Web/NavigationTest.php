<?php

use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('las cuatro pantallas responden', function (string $path, string $text): void {
    app(AppSettings::class)->setRfc('BBBB010101BBB');

    $this->get($path)->assertOk()->assertSee($text);
})->with([
    'procesar' => ['/', 'Procesar CFDIs'],
    'documentos' => ['/documentos', 'Documentos'],
    'ajustes' => ['/ajustes', 'Ajustes'],
    'contrapartes' => ['/contrapartes', 'Contrapartes'],
]);

it('la navegacion muestra las cuatro pantallas', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Procesar')
        ->assertSee('Documentos')
        ->assertSee('Contrapartes')
        ->assertSee('Ajustes');
});

// Este test es el que evita que el stack de autenticacion regrese sin que nadie
// se de cuenta.
it('no queda ninguna ruta de autenticacion', function (string $name): void {
    expect(Route::has($name))->toBeFalse();
})->with([
    'login', 'register', 'logout', 'dashboard', 'profile.edit',
    'password.request', 'password.reset', 'two-factor.login',
    'verification.notice', 'appearance.edit', 'security.edit',
    'well-known.passkeys',
]);

it('las URLs del starter kit ya no existen', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with(['/login', '/register', '/dashboard', '/settings/profile', '/settings/appearance']);

it('no hay modelo de usuarios ni tabla users', function (): void {
    expect(class_exists(User::class))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeFalse();
});
