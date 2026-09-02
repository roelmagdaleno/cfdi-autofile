<?php

use App\Livewire\EditSettings;
use App\Support\AppSettings;
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
