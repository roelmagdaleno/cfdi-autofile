<?php

use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('all four screens respond', function (string $path, string $text): void {
    app(AppSettings::class)->setRfc('BBBB010101BBB');

    $this->get($path)->assertOk()->assertSee($text);
})->with([
    'process' => ['/', 'Process CFDIs'],
    'documents' => ['/documents', 'Documents'],
    'settings' => ['/settings', 'Settings'],
    'counterparties' => ['/counterparties', 'Counterparties'],
]);

it('the navigation shows all four screens', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Process')
        ->assertSee('Documents')
        ->assertSee('Counterparties')
        ->assertSee('Settings');
});

// This test is what keeps the authentication stack from coming back without
// anyone noticing.
it('no authentication route is left', function (string $name): void {
    expect(Route::has($name))->toBeFalse();
})->with([
    'login', 'register', 'logout', 'dashboard', 'profile.edit',
    'password.request', 'password.reset', 'two-factor.login',
    'verification.notice', 'appearance.edit', 'security.edit',
    'well-known.passkeys',
]);

it('the starter kit URLs no longer exist', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with(['/login', '/register', '/dashboard', '/settings/profile', '/settings/appearance']);

it('there is no user model and no users table', function (): void {
    expect(class_exists(User::class))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeFalse();
});
