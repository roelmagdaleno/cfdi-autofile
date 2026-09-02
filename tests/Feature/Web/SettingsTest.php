<?php

use App\Cloud\Data\CloudProvider;
use App\Livewire\EditSettings;
use App\Support\AppSettings;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

it('stores the RFC uppercased', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', '  bbbb010101bbb ')
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('rfc', 'BBBB010101BBB');

    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('stores the template', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', '{type} {date} - {uuid}')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(AppSettings::class)->template())->toBe('{type} {date} - {uuid}');
});

it('rejects an RFC with an invalid format', function (string $rfc): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', $rfc)
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->call('save')
        ->assertHasErrors('rfc');

    $this->assertDatabaseMissing('settings', ['key' => 'rfc']);
})->with([
    'empty' => [''],
    'plain text' => ['not-an-rfc'],
    'too short' => ['AAA0101'],
    'no digits' => ['AAAAAAAAAAAA'],
]);

it('rejects a template with no placeholders', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', 'invoice')
        ->call('save')
        ->assertHasErrors('template');
});

it('preloads the stored settings', function (): void {
    $settings = app(AppSettings::class);
    $settings->setRfc('BBBB010101BBB');
    $settings->setTemplate('{type} {date}');

    Livewire::test(EditSettings::class)
        ->assertSet('rfc', 'BBBB010101BBB')
        ->assertSet('template', '{type} {date}');
});

it('shows an example built with the current template', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->assertSee('2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml');
});

it('leaves an unknown placeholder literal in the example', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{date} {folio}')
        ->assertSee('2026-02-14 {folio}.xml');
});

it('inserts the piece where the cursor is', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{date} - {amount}')
        ->call('insertPlaceholder', 'type', 7, 7)
        ->assertSet('template', '{date} {type}- {amount}');
});

it('inserts the piece at the end when there is no cursor', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{date} ')
        ->call('insertPlaceholder', 'uuid')
        ->assertSet('template', '{date} {uuid}')
        ->assertSee('2026-02-14 A1B2C3D4-E5F6-4789-ABCD-1234567890AB.xml');
});

it('replaces the selected text with the piece', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{date} DELETEME')
        ->call('insertPlaceholder', 'type', 7, 15)
        ->assertSet('template', '{date} {type}');
});

it('clamps a selection that runs past the template', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{date}')
        ->call('insertPlaceholder', 'type', 999, 999)
        ->assertSet('template', '{date}{type}');
});

it('ignores a piece that is not in the catalog', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{date}')
        ->call('insertPlaceholder', 'folio')
        ->assertSet('template', '{date}');
});

it('restores the default template', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{uuid}')
        ->call('resetTemplate')
        ->assertSet('template', AppSettings::DEFAULT_TEMPLATE);
});

it('shows the example as income when the type changes', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{type} {date}')
        ->set('previewType', 'I')
        ->assertSee('I 2026-02-14.xml');
});

it('stores the type labels trimmed', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->set('letterIncome', ' E ')
        ->set('letterExpense', 'R')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('letterIncome', 'E');

    $letters = app(AppSettings::class)->typeLetters();

    expect($letters->income)->toBe('E')
        ->and($letters->expense)->toBe('R');
});

// The example reads the labels straight off the form, so it reacts before the
// settings are saved.
it('shows the example with the labels being typed', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{type} {date}')
        ->set('previewType', 'I')
        ->set('letterIncome', 'EMI')
        ->assertSee('EMI 2026-02-14.xml');
});

it('rejects a type label that cannot be used', function (string $letter): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->set('letterExpense', $letter)
        ->call('save')
        ->assertHasErrors('letterExpense');

    $this->assertDatabaseMissing('settings', ['key' => 'letter_expense']);
})->with([
    'empty' => [''],
    'blank' => ['   '],
    'too long' => ['Recibidas'],
    'a slash' => ['a/b'],
    'a question mark' => ['a?b'],
]);

/**
 * Fills the fields the form always validates, so a test can focus on the Drive
 * ones.
 */
function validSettings(): Testable
{
    return Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', AppSettings::DEFAULT_TEMPLATE);
}

it('has the drive integration off by default', function (): void {
    Livewire::test(EditSettings::class)
        ->assertSet('cloudEnabled', false)
        ->assertSet('cloudConnected', false)
        ->assertSet('googleIncomeFolder', '{year} - Emitidas')
        ->assertSet('googleExpenseFolder', '{year} - Recibidas')
        // The default alias is an implementation detail: an empty box reads as
        // "the top of My Drive".
        ->assertSet('googleRootFolderId', '');
});

it('saves the drive configuration', function (): void {
    app(AppSettings::class)->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');

    validSettings()
        ->set('cloudEnabled', true)
        ->set('googleRootFolderId', '1a2b3c')
        ->set('googleIncomeFolder', 'Emitidas {year}')
        ->set('googleExpenseFolder', 'Recibidas {year}')
        ->call('save')
        ->assertHasNoErrors();

    $google = app(AppSettings::class)->googleDrive();

    expect(app(AppSettings::class)->cloud()->enabled)->toBeTrue()
        ->and($google->rootFolderId)->toBe('1a2b3c')
        ->and($google->incomeFolder)->toBe('Emitidas {year}');
});

it('falls back to the root of my drive when no folder id is given', function (): void {
    app(AppSettings::class)->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');

    validSettings()
        ->set('cloudEnabled', true)
        ->set('googleRootFolderId', '   ')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(AppSettings::class)->googleDrive()->rootFolderId)->toBe('root');
});

// Turning the switch on without an authorized account would make every run fail
// identically, twenty rows at a time.
it('refuses to enable the integration without a connected account', function (): void {
    validSettings()
        ->set('cloudEnabled', true)
        ->call('save')
        ->assertHasErrors('cloudEnabled');

    expect(app(AppSettings::class)->cloud()->enabled)->toBeFalse();
});

it('rejects a folder name that cannot file by year', function (string $folder): void {
    app(AppSettings::class)->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');

    validSettings()
        ->set('cloudEnabled', true)
        ->set('googleIncomeFolder', $folder)
        ->call('save')
        ->assertHasErrors('googleIncomeFolder');
})->with([
    'empty' => [''],
    'no year token' => ['Emitidas'],
    'wrong token' => ['{año} - Emitidas'],
    'a slash' => ['{year}/Emitidas'],
]);

// Turning the integration off must never be blocked by the folder fields.
it('allows saving with empty folder names while the integration is off', function (): void {
    validSettings()
        ->set('cloudEnabled', false)
        ->set('googleIncomeFolder', '')
        ->set('googleExpenseFolder', '')
        ->call('save')
        ->assertHasNoErrors();
});

it('previews the folders of the current year', function (): void {
    $year = now()->format('Y');

    validSettings()
        ->set('googleIncomeFolder', 'Emitidas {year}')
        ->set('cloudEnabled', true)
        ->assertSee("Emitidas {$year}")
        ->assertSee("{$year} - Recibidas");
});

it('forgets the account but keeps the folders when disconnecting', function (): void {
    $settings = app(AppSettings::class);
    $settings->setCredential(CloudProvider::GoogleDrive, 'refresh-token-abc');
    $settings->setAccountLabel(CloudProvider::GoogleDrive, 'facturas@example.com');

    Livewire::test(EditSettings::class)
        ->assertSet('cloudConnected', true)
        ->assertSet('cloudAccount', 'facturas@example.com')
        ->call('disconnectCloud')
        ->assertSet('cloudConnected', false)
        ->assertSet('cloudAccount', '')
        ->assertSet('googleIncomeFolder', '{year} - Emitidas');

    expect($settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse();
});

it('verifies the folders of the current year against drive', function (): void {
    $files = fakeCloud();
    $year = (int) now()->format('Y');

    Livewire::test(EditSettings::class)
        ->set('cloudEnabled', true)
        ->call('verifyCloudDestinations')
        ->assertHasNoErrors();

    expect($files->created)->toBe(["{$year} - Emitidas", "{$year} - Recibidas"]);
});

it('reports a drive failure when verifying instead of throwing', function (): void {
    $files = fakeCloud();
    $year = (int) now()->format('Y');
    $files->failFolder = "{$year} - Emitidas";

    Livewire::test(EditSettings::class)
        ->set('cloudEnabled', true)
        ->call('verifyCloudDestinations')
        ->assertHasNoErrors();

    expect($files->created)->toBe([]);
});

// The screen used to show "connected as" followed by nothing whenever the
// address had not made it into the settings.
it('fills in a missing account address on the next visit', function (): void {
    fakeCloud();
    app(AppSettings::class)->setAccountLabel(CloudProvider::GoogleDrive, '');

    Livewire::test(EditSettings::class)
        ->assertSet('cloudConnected', true)
        ->assertSet('cloudAccount', 'facturas@example.com')
        ->assertSee('facturas@example.com');

    expect(app(AppSettings::class)->accountLabel(CloudProvider::GoogleDrive))->toBe('facturas@example.com');
});

it('still reports the account as connected when drive will not name it', function (): void {
    $files = fakeCloud();
    $files->failAbout = true;
    app(AppSettings::class)->setAccountLabel(CloudProvider::GoogleDrive, '');

    Livewire::test(EditSettings::class)
        ->assertSet('cloudConnected', true)
        ->assertSet('cloudAccount', '')
        ->assertSee(__('ui.cloud.connected_unknown', ['provider' => 'Google Drive']))
        ->assertSee(__('ui.cloud.disconnect'));
});

it('renders the template section as a labelled group', function (): void {
    Livewire::test(EditSettings::class)
        ->assertSee('Name template')
        ->assertSeeHtml('aria-label="Name template"');
});
