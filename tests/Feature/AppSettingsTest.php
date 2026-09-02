<?php

use App\Cfdi\Naming\TypeLetters;
use App\Drive\Data\DriveConfig;
use App\Models\Setting;
use App\Support\AppSettings;

beforeEach(function (): void {
    $this->settings = new AppSettings;
});

it('has no RFC by default', function (): void {
    expect($this->settings->rfc())->toBeNull();
});

it('stores the RFC uppercased and trimmed', function (): void {
    $this->settings->setRfc('  bbbb010101bbb ');

    expect($this->settings->rfc())->toBe('BBBB010101BBB');
    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('uses the default template when nothing is stored', function (): void {
    expect($this->settings->template())->toBe('{date} {type} {counterparty} - {amount}{credit_note}');
});

it('stores the template verbatim', function (): void {
    $this->settings->setTemplate('{type} {date} - {counterparty}');

    expect($this->settings->template())->toBe('{type} {date} - {counterparty}');
});

it('falls back to the default template when the stored one is empty', function (): void {
    $this->settings->setTemplate('');

    expect($this->settings->template())->toBe(AppSettings::DEFAULT_TEMPLATE);
});

it('uses the canonical letters when nothing is stored', function (): void {
    $letters = $this->settings->typeLetters();

    expect($letters->income)->toBe('I')
        ->and($letters->expense)->toBe('G');
});

it('stores the letters trimmed', function (): void {
    $this->settings->setTypeLetters(new TypeLetters(income: ' E ', expense: 'R'));

    $letters = $this->settings->typeLetters();

    expect($letters->income)->toBe('E')
        ->and($letters->expense)->toBe('R');
    $this->assertDatabaseHas('settings', ['key' => 'letter_income', 'value' => 'E']);
    $this->assertDatabaseHas('settings', ['key' => 'letter_expense', 'value' => 'R']);
});

it('falls back to the canonical letter when the stored one is blank', function (): void {
    $this->settings->setTypeLetters(new TypeLetters(income: '', expense: 'R'));

    $letters = $this->settings->typeLetters();

    expect($letters->income)->toBe('I')
        ->and($letters->expense)->toBe('R');
});

it('overwrites instead of duplicating', function (): void {
    $this->settings->setRfc('AAA010101AAA');
    $this->settings->setRfc('BBB020202BBB');

    expect($this->settings->rfc())->toBe('BBB020202BBB');
    expect(Setting::query()->where('key', 'rfc')->count())->toBe(1);
});

it('defaults the drive integration to off with the spanish folder names', function (): void {
    $drive = $this->settings->drive();

    expect($drive->enabled)->toBeFalse()
        ->and($drive->rootFolderId)->toBe('root')
        ->and($drive->incomeFolder)->toBe('{year} - Emitidas')
        ->and($drive->expenseFolder)->toBe('{year} - Recibidas')
        ->and($this->settings->isDriveConnected())->toBeFalse()
        ->and($this->settings->driveAccountEmail())->toBeNull();
});

it('stores and reads back the drive configuration', function (): void {
    $this->settings->setDrive(new DriveConfig(
        enabled: true,
        rootFolderId: '  1a2b3c  ',
        incomeFolder: '  Emitidas {year}  ',
        expenseFolder: 'Recibidas {year}',
    ));

    $drive = $this->settings->drive();

    expect($drive->enabled)->toBeTrue()
        ->and($drive->rootFolderId)->toBe('1a2b3c')
        ->and($drive->incomeFolder)->toBe('Emitidas {year}')
        ->and($drive->expenseFolder)->toBe('Recibidas {year}');
});

// A refresh token is a long-lived password for the user's whole Drive.
it('encrypts the drive refresh token at rest', function (): void {
    $this->settings->setDriveRefreshToken('super-secret');

    $stored = Setting::query()->where('key', AppSettings::KEY_DRIVE_REFRESH_TOKEN)->value('value');

    expect($stored)->not->toBe('super-secret')
        ->and($this->settings->driveRefreshToken())->toBe('super-secret');
});

// `APP_KEY` was rotated: the token is unrecoverable, and reporting it as "not
// connected" sends the user to reconnect instead of blowing up mid-run.
it('treats an undecryptable token as not connected', function (): void {
    Setting::query()->create(['key' => AppSettings::KEY_DRIVE_REFRESH_TOKEN, 'value' => 'not-encrypted-at-all']);

    expect($this->settings->driveRefreshToken())->toBeNull()
        ->and($this->settings->isDriveConnected())->toBeFalse();
});

it('keeps the folder configuration when the account is disconnected', function (): void {
    $this->settings->setDrive(new DriveConfig(true, '1a2b3c', 'Emitidas {year}', 'Recibidas {year}'));
    $this->settings->setDriveRefreshToken('super-secret');
    $this->settings->setDriveAccountEmail('facturas@example.com');

    $this->settings->disconnectDrive();

    expect($this->settings->isDriveConnected())->toBeFalse()
        ->and($this->settings->driveAccountEmail())->toBeNull()
        ->and($this->settings->drive()->rootFolderId)->toBe('1a2b3c');
});
