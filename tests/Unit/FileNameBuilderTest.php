<?php

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\FileNameBuilder;
use App\Cfdi\Naming\NameSanitizer;
use App\Cfdi\Naming\TypeLetters;
use App\Support\AppSettings;
use Illuminate\Support\Facades\App;

beforeEach(function (): void {
    $this->builder = new FileNameBuilder(new NameSanitizer);
});

it('builds the name with the default template', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '11600.00', invoiceType: 'I');

    $name = $this->builder->build($data, TypeLetter::Expense, 'Contador', AppSettings::DEFAULT_TEMPLATE, '.pdf');

    expect($name)->toBe('2026-02-14 G Contador - 11600.00.pdf');
});

it('marks the credit note and sanitizes', function (): void {
    $data = new CfdiData(date: '2026-03-01T09:00:00', total: '$1,160.00', invoiceType: 'E');

    $name = $this->builder->build($data, TypeLetter::Expense, 'Cafe Piñón', AppSettings::DEFAULT_TEMPLATE, '.xml');

    expect($name)->toBe('2026-03-01 G Cafe Pinon - 1160.00 (credit note).xml')
        ->and($name)->not->toContain(',')
        ->and($name)->not->toContain('$')
        ->and($name)->not->toContain('ñ');
});

// The credit-note suffix reaches the user's disk, so a Spanish-locale install
// keeps producing exactly the file names it produced before the translation.
it('uses the Spanish credit note suffix under the es locale', function (): void {
    App::setLocale('es');

    $data = new CfdiData(date: '2026-03-01T09:00:00', total: '1160.00', invoiceType: 'E');

    $name = $this->builder->build($data, TypeLetter::Expense, 'Contador', AppSettings::DEFAULT_TEMPLATE, '.xml');

    expect($name)->toBe('2026-03-01 G Contador - 1160.00 (nota de credito).xml');
});

it('accepts a custom template with a uuid', function (): void {
    $data = new CfdiData(
        date: '2026-02-14T11:32:05',
        total: '500',
        invoiceType: 'I',
        uuid: 'A1B2C3D4-E5F6-4789-ABCD-1234567890AB',
    );

    $name = $this->builder->build($data, TypeLetter::Income, 'Cliente', '{type} {date} - {uuid}', '.xml');

    expect($name)->toBe('I 2026-02-14 - A1B2C3D4-E5F6-4789-ABCD-1234567890AB.xml');
});

it('leaves an unknown placeholder literal', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '100.00');

    $name = $this->builder->build($data, TypeLetter::Income, 'X', '{date} {folio}', '.xml');

    expect($name)->toBe('2026-02-14 {folio}.xml');
});

// `{type}` renders the configured label, not the enum's backing value: somebody
// who thinks in "emitido / recibido" gets `E` and `R` on disk.
it('writes the configured labels', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '11600.00', invoiceType: 'I');
    $letters = new TypeLetters(income: 'E', expense: 'R');

    expect($this->builder->build($data, TypeLetter::Expense, 'Contador', AppSettings::DEFAULT_TEMPLATE, '.pdf', $letters))
        ->toBe('2026-02-14 R Contador - 11600.00.pdf')
        ->and($this->builder->build($data, TypeLetter::Income, 'Cliente', AppSettings::DEFAULT_TEMPLATE, '.pdf', $letters))
        ->toBe('2026-02-14 E Cliente - 11600.00.pdf');
});

it('writes a label longer than one character', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '11600.00', invoiceType: 'I');

    $name = $this->builder->build(
        $data,
        TypeLetter::Expense,
        'Contador',
        AppSettings::DEFAULT_TEMPLATE,
        '.xml',
        new TypeLetters(income: 'EMI', expense: 'REC'),
    );

    expect($name)->toBe('2026-02-14 REC Contador - 11600.00.xml');
});

// Configuring the labels does not give `Unknown` one: it is an error case.
it('turns the unknown type letter into a dash even with labels configured', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '100.00');

    $name = $this->builder->build(
        $data,
        TypeLetter::Unknown,
        'Proveedor',
        AppSettings::DEFAULT_TEMPLATE,
        '.xml',
        new TypeLetters(income: 'E', expense: 'R'),
    );

    expect($name)->toBe('2026-02-14 - Proveedor - 100.00.xml');
});

it('turns the unknown type letter into a dash', function (): void {
    $data = new CfdiData(date: '2026-02-14T11:32:05', total: '100.00');

    $name = $this->builder->build($data, TypeLetter::Unknown, 'Proveedor', AppSettings::DEFAULT_TEMPLATE, '.xml');

    expect($name)->toBe('2026-02-14 - Proveedor - 100.00.xml');
});

it('uses "cfdi" when the name comes out empty', function (): void {
    $name = $this->builder->build(new CfdiData, TypeLetter::Unknown, '', '{date}{amount}', '.pdf');

    expect($name)->toBe('cfdi.pdf');
});

it('formats the amount with two decimals', function (?string $total, string $expected): void {
    $data = new CfdiData(date: '2026-01-01', total: $total);

    expect($this->builder->build($data, TypeLetter::Income, 'X', '{amount}', '.xml'))->toBe($expected.'.xml');
})->with([
    ['11600', '11600.00'],
    ['$1,160.5', '1160.50'],
    ['0', '0.00'],
    [null, 'cfdi'],
    ['abc', 'abc'],
]);
