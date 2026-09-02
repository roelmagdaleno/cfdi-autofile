<?php

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\FileNameBuilder;
use App\Cfdi\Naming\NameSanitizer;
use App\Support\AppSettings;

beforeEach(function (): void {
    $this->builder = new FileNameBuilder(new NameSanitizer);
});

it('construye el nombre con la plantilla default', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '11600.00', invoiceType: 'I');

    $name = $this->builder->build($data, TypeLetter::Expense, 'Contador', AppSettings::DEFAULT_TEMPLATE, '.pdf');

    expect($name)->toBe('2026-02-14 G Contador - 11600.00.pdf');
});

it('marca la nota de credito y sanitiza', function (): void {
    $data = new CfdiData(date: '2026-03-01T09:00:00', total: '$1,160.00', invoiceType: 'E');

    $name = $this->builder->build($data, TypeLetter::Expense, 'Cafe Piñón', AppSettings::DEFAULT_TEMPLATE, '.xml');

    expect($name)->toBe('2026-03-01 G Cafe Pinon - 1160.00 (nota de credito).xml')
        ->and($name)->not->toContain(',')
        ->and($name)->not->toContain('$')
        ->and($name)->not->toContain('ñ');
});

it('acepta una plantilla personalizada con uuid', function (): void {
    $data = new CfdiData(
        date: '2026-02-14T11:32:05',
        total: '500',
        invoiceType: 'I',
        uuid: 'A1B2C3D4-E5F6-4789-ABCD-1234567890AB',
    );

    $name = $this->builder->build($data, TypeLetter::Income, 'Cliente', '{tipo} {fecha} - {uuid}', '.xml');

    expect($name)->toBe('I 2026-02-14 - A1B2C3D4-E5F6-4789-ABCD-1234567890AB.xml');
});

it('deja literal un placeholder desconocido', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '100.00');

    $name = $this->builder->build($data, TypeLetter::Income, 'X', '{fecha} {folio}', '.xml');

    expect($name)->toBe('2026-02-14 {folio}.xml');
});

it('convierte la letra de tipo desconocida en guion', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '100.00');

    $name = $this->builder->build($data, TypeLetter::Unknown, 'Proveedor', AppSettings::DEFAULT_TEMPLATE, '.xml');

    expect($name)->toBe('2026-02-14 - Proveedor - 100.00.xml');
});

it('usa "cfdi" cuando el nombre queda vacio', function (): void {
    $name = $this->builder->build(new CfdiData, TypeLetter::Unknown, '', '{fecha}{monto}', '.pdf');

    expect($name)->toBe('cfdi.pdf');
});

it('formatea el monto con dos decimales', function (?string $total, string $expected): void {
    $data = new CfdiData(date: '2026-01-01', total: $total);

    expect($this->builder->build($data, TypeLetter::Income, 'X', '{monto}', '.xml'))->toBe($expected.'.xml');
})->with([
    ['11600', '11600.00'],
    ['$1,160.5', '1160.50'],
    ['0', '0.00'],
    [null, 'cfdi'],
    ['abc', 'abc'],
]);
