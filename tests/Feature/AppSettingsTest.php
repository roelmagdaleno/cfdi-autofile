<?php

use App\Cfdi\Naming\TypeLetters;
use App\Cloud\Data\CloudProvider;
use App\Cloud\Google\GoogleDriveConfig;
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

it('defaults the integration to off, on google drive, with the spanish folder names', function (): void {
    $cloud = $this->settings->cloud();
    $drive = $this->settings->googleDrive();

    expect($cloud->enabled)->toBeFalse()
        ->and($cloud->provider)->toBe(CloudProvider::GoogleDrive)
        ->and($drive->rootFolderId)->toBe('root')
        ->and($drive->incomeFolder)->toBe('{year} - Emitidas')
        ->and($drive->expenseFolder)->toBe('{year} - Recibidas')
        ->and($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse()
        ->and($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBeNull();
});

it('stores and reads back the drive configuration', function (): void {
    $this->settings->setGoogleDrive(new GoogleDriveConfig(
        rootFolderId: '  1a2b3c  ',
        incomeFolder: '  Emitidas {year}  ',
        expenseFolder: 'Recibidas {year}',
    ));

    $drive = $this->settings->googleDrive();

    expect($drive->rootFolderId)->toBe('1a2b3c')
        ->and($drive->incomeFolder)->toBe('Emitidas {year}')
        ->and($drive->expenseFolder)->toBe('Recibidas {year}');
});

// A refresh token is a long-lived password for the user's whole Drive.
it('encrypts the drive refresh token at rest', function (): void {
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'super-secret');

    $stored = Setting::query()->where('key', 'cloud_google_drive_'.AppSettings::SUFFIX_CREDENTIAL)->value('value');

    expect($stored)->not->toBe('super-secret')
        ->and($this->settings->credential(CloudProvider::GoogleDrive))->toBe('super-secret');
});

// `APP_KEY` was rotated: the token is unrecoverable, and reporting it as "not
// connected" sends the user to reconnect instead of blowing up mid-run.
it('treats an undecryptable token as not connected', function (): void {
    Setting::query()->create(['key' => 'cloud_google_drive_'.AppSettings::SUFFIX_CREDENTIAL, 'value' => 'not-encrypted-at-all']);

    expect($this->settings->credential(CloudProvider::GoogleDrive))->toBeNull()
        ->and($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse();
});

it('keeps the folder configuration when the account is disconnected', function (): void {
    $this->settings->setGoogleDrive(new GoogleDriveConfig('1a2b3c', 'Emitidas {year}', 'Recibidas {year}'));
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'super-secret');
    $this->settings->setAccountLabel(CloudProvider::GoogleDrive, 'facturas@example.com');

    $this->settings->disconnectCloud(CloudProvider::GoogleDrive);

    expect($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse()
        ->and($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBeNull()
        ->and($this->settings->googleDrive()->rootFolderId)->toBe('1a2b3c');
});

// A row somebody edited by hand must not take the settings screen down with it.
it('falls back to the default provider when the stored one is unreadable', function (): void {
    Setting::query()->create(['key' => AppSettings::KEY_CLOUD_PROVIDER, 'value' => 'dropbox-someday']);

    expect($this->settings->cloudProvider())->toBe(CloudProvider::default());
});

// What makes switching platforms cheap: connecting one provider says nothing
// about any other, so the ids and tokens of the old one survive the switch.
it('keeps credentials in a namespace of their own provider', function (): void {
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'google-secret');

    expect($this->settings->credential(CloudProvider::GoogleDrive))->toBe('google-secret');

    $this->assertDatabaseHas('settings', ['key' => 'cloud_google_drive_credential']);
    $this->assertDatabaseMissing('settings', ['key' => 'cloud_credential']);
});
