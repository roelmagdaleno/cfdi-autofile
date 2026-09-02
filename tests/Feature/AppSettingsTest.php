<?php

use App\Models\Setting;
use App\Support\AppSettings;

beforeEach(function (): void {
    $this->settings = new AppSettings;
});

it('no tiene RFC por default', function (): void {
    expect($this->settings->rfc())->toBeNull();
});

it('guarda el RFC en mayusculas y sin espacios', function (): void {
    $this->settings->setRfc('  bbbb010101bbb ');

    expect($this->settings->rfc())->toBe('BBBB010101BBB');
    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('usa la plantilla default cuando no hay nada guardado', function (): void {
    expect($this->settings->template())->toBe('{fecha} {tipo} {contraparte} - {monto}{nota_credito}');
});

it('guarda la plantilla tal cual', function (): void {
    $this->settings->setTemplate('{tipo} {fecha} - {contraparte}');

    expect($this->settings->template())->toBe('{tipo} {fecha} - {contraparte}');
});

it('cae en la plantilla default si la guardada esta vacia', function (): void {
    $this->settings->setTemplate('');

    expect($this->settings->template())->toBe(AppSettings::DEFAULT_TEMPLATE);
});

it('sobreescribe en vez de duplicar', function (): void {
    $this->settings->setRfc('AAA010101AAA');
    $this->settings->setRfc('BBB020202BBB');

    expect($this->settings->rfc())->toBe('BBB020202BBB');
    expect(Setting::query()->where('key', 'rfc')->count())->toBe(1);
});
