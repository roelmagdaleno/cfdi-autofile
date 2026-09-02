<?php

use App\Livewire\EditSettings;
use App\Support\AppSettings;
use Livewire\Livewire;

it('guarda el RFC en mayusculas', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', '  bbbb010101bbb ')
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('rfc', 'BBBB010101BBB');

    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('guarda la plantilla', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', '{tipo} {fecha} - {uuid}')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(AppSettings::class)->template())->toBe('{tipo} {fecha} - {uuid}');
});

it('rechaza un RFC con formato invalido', function (string $rfc): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', $rfc)
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->call('save')
        ->assertHasErrors('rfc');

    $this->assertDatabaseMissing('settings', ['key' => 'rfc']);
})->with([
    'vacio' => [''],
    'texto' => ['no-es-un-rfc'],
    'corto' => ['AAA0101'],
    'sin digitos' => ['AAAAAAAAAAAA'],
]);

it('rechaza una plantilla sin placeholders', function (): void {
    Livewire::test(EditSettings::class)
        ->set('rfc', 'BBBB010101BBB')
        ->set('template', 'factura')
        ->call('save')
        ->assertHasErrors('template');
});

it('precarga los ajustes guardados', function (): void {
    $settings = app(AppSettings::class);
    $settings->setRfc('BBBB010101BBB');
    $settings->setTemplate('{tipo} {fecha}');

    Livewire::test(EditSettings::class)
        ->assertSet('rfc', 'BBBB010101BBB')
        ->assertSet('template', '{tipo} {fecha}');
});

it('muestra un ejemplo con la plantilla actual', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', AppSettings::DEFAULT_TEMPLATE)
        ->assertSee('2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml');
});

it('deja literal un placeholder desconocido en el ejemplo', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{fecha} {folio}')
        ->assertSee('2026-02-14 {folio}.xml');
});

it('inserta la pieza donde este el cursor', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{fecha} - {monto}')
        ->call('insertPlaceholder', 'tipo', 8, 8)
        ->assertSet('template', '{fecha} {tipo}- {monto}');
});

it('inserta la pieza al final cuando no hay cursor', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{fecha} ')
        ->call('insertPlaceholder', 'uuid')
        ->assertSet('template', '{fecha} {uuid}')
        ->assertSee('2026-02-14 A1B2C3D4-E5F6-4789-ABCD-1234567890AB.xml');
});

it('reemplaza el texto seleccionado por la pieza', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{fecha} BORRAME')
        ->call('insertPlaceholder', 'tipo', 8, 15)
        ->assertSet('template', '{fecha} {tipo}');
});

it('recorta una seleccion que se sale de la plantilla', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{fecha}')
        ->call('insertPlaceholder', 'tipo', 999, 999)
        ->assertSet('template', '{fecha}{tipo}');
});

it('ignora una pieza que no esta en el catalogo', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{fecha}')
        ->call('insertPlaceholder', 'folio')
        ->assertSet('template', '{fecha}');
});

it('restaura la plantilla por defecto', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{uuid}')
        ->call('resetTemplate')
        ->assertSet('template', AppSettings::DEFAULT_TEMPLATE);
});

it('muestra el ejemplo como ingreso al cambiar el tipo', function (): void {
    Livewire::test(EditSettings::class)
        ->set('template', '{tipo} {fecha}')
        ->set('previewType', 'I')
        ->assertSee('I 2026-02-14.xml');
});
