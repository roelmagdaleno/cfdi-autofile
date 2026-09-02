<?php

use App\Cloud\CloudAccount;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Data\CloudProvider;
use App\Support\AppSettings;
use Tests\Support\FakeCloudFiles;

beforeEach(function (): void {
    $this->settings = app(AppSettings::class);
});

it('has no account while nothing is connected', function (): void {
    expect(app(CloudAccount::class)->label())->toBeNull();
});

// Asking Drive without a token would throw, and there is nothing to ask about.
it('never calls drive while nothing is connected', function (): void {
    $files = new FakeCloudFiles;
    app()->instance(CloudFiles::class, $files);

    app(CloudAccount::class)->label();

    expect($files->email)->toBe('facturas@example.com');
    expect($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBeNull();
});

it('returns the stored address without asking drive again', function (): void {
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');
    $this->settings->setAccountLabel(CloudProvider::GoogleDrive, 'ya@guardado.com');

    $files = new FakeCloudFiles;
    $files->email = 'otro@example.com';
    app()->instance(CloudFiles::class, $files);

    expect(app(CloudAccount::class)->label())->toBe('ya@guardado.com');
});

// The bug this class exists for: a connection whose address never made it in
// used to show "connected as" followed by nothing, forever.
it('asks drive and remembers the address when it is missing', function (): void {
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');

    expect($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBeNull();

    expect(app(CloudAccount::class)->label())->toBe('facturas@example.com')
        // Remembered, so the next screen does not pay for another call.
        ->and($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBe('facturas@example.com');
});

// Not knowing the address is cosmetic: it must never surface as an error.
it('reports no address instead of failing when drive is unreachable', function (): void {
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');

    $files = new FakeCloudFiles;
    $files->failAbout = true;
    app()->instance(CloudFiles::class, $files);

    expect(app(CloudAccount::class)->label())->toBeNull()
        ->and($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBeNull();
});

it('does not store a blank address', function (): void {
    $this->settings->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');

    $files = new FakeCloudFiles;
    $files->email = '';
    app()->instance(CloudFiles::class, $files);

    expect(app(CloudAccount::class)->label())->toBeNull();

    // "Not known yet" has one representation, so a later visit retries.
    $this->assertDatabaseMissing('settings', ['key' => 'cloud_google_drive_'.AppSettings::SUFFIX_ACCOUNT]);
});
