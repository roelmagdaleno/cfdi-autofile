<?php

use App\Drive\Contracts\DriveFiles;
use App\Drive\DriveAccount;
use App\Support\AppSettings;
use Tests\Support\FakeDriveFiles;

beforeEach(function (): void {
    $this->settings = app(AppSettings::class);
});

it('has no account while nothing is connected', function (): void {
    expect(app(DriveAccount::class)->email())->toBeNull();
});

// Asking Drive without a token would throw, and there is nothing to ask about.
it('never calls drive while nothing is connected', function (): void {
    $files = new FakeDriveFiles;
    app()->instance(DriveFiles::class, $files);

    app(DriveAccount::class)->email();

    expect($files->email)->toBe('facturas@example.com');
    expect($this->settings->driveAccountEmail())->toBeNull();
});

it('returns the stored address without asking drive again', function (): void {
    $this->settings->setDriveRefreshToken('refresh-token-abc');
    $this->settings->setDriveAccountEmail('ya@guardado.com');

    $files = new FakeDriveFiles;
    $files->email = 'otro@example.com';
    app()->instance(DriveFiles::class, $files);

    expect(app(DriveAccount::class)->email())->toBe('ya@guardado.com');
});

// The bug this class exists for: a connection whose address never made it in
// used to show "connected as" followed by nothing, forever.
it('asks drive and remembers the address when it is missing', function (): void {
    $this->settings->setDriveRefreshToken('refresh-token-abc');

    expect($this->settings->driveAccountEmail())->toBeNull();

    expect(app(DriveAccount::class)->email())->toBe('facturas@example.com')
        // Remembered, so the next screen does not pay for another call.
        ->and($this->settings->driveAccountEmail())->toBe('facturas@example.com');
});

// Not knowing the address is cosmetic: it must never surface as an error.
it('reports no address instead of failing when drive is unreachable', function (): void {
    $this->settings->setDriveRefreshToken('refresh-token-abc');

    $files = new FakeDriveFiles;
    $files->failAbout = true;
    app()->instance(DriveFiles::class, $files);

    expect(app(DriveAccount::class)->email())->toBeNull()
        ->and($this->settings->driveAccountEmail())->toBeNull();
});

it('does not store a blank address', function (): void {
    $this->settings->setDriveRefreshToken('refresh-token-abc');

    $files = new FakeDriveFiles;
    $files->email = '';
    app()->instance(DriveFiles::class, $files);

    expect(app(DriveAccount::class)->email())->toBeNull();

    // "Not known yet" has one representation, so a later visit retries.
    $this->assertDatabaseMissing('settings', ['key' => AppSettings::KEY_DRIVE_ACCOUNT_EMAIL]);
});
