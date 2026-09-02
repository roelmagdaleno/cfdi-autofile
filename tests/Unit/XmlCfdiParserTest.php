<?php

use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Parsing\XmlCfdiParser;

/**
 * Writes an XML derived from the fixture to a temporary file and returns its path.
 */
function xmlVariant(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'cfdi').'.xml';
    file_put_contents($path, $contents);

    return $path;
}

function exampleCfdi(): string
{
    return (string) file_get_contents(cfdiFixture('example_cfdi.xml'));
}

beforeEach(function (): void {
    $this->parser = new XmlCfdiParser;
});

it('extracts every field of a CFDI 4.0', function (): void {
    $data = $this->parser->parse(cfdiFixture('example_cfdi.xml'));

    expect($data->date)->toBe('2026-02-14T11:32:05')
        ->and($data->total)->toBe('11600.00')
        ->and($data->invoiceType)->toBe('I')
        ->and($data->issuerRfc)->toBe('AAA010101AAA')
        ->and($data->issuerName)->toBe('SERVICIOS CONTABLES DEL NORTE SA DE CV')
        ->and($data->recipientRfc)->toBe('BBBB010101BBB')
        ->and($data->recipientName)->toBe('RECEPTOR DE PRUEBA')
        ->and($data->uuid)->toBe('A1B2C3D4-E5F6-4789-ABCD-1234567890AB')
        ->and($data->replacedUuid)->toBeNull()
        ->and($data->confidence)->toBe(Confidence::High)
        ->and($data->source)->toBe(SourceFormat::Xml)
        ->and($data->isCreditNote())->toBeFalse();
});

it('rejects an XML that is not a CFDI', function (): void {
    expect(fn () => $this->parser->parse(cfdiFixture('not_a_cfdi.xml')))
        ->toThrow(CfdiRejectedException::class, 'not a valid CFDI');
});

it('rejects a malformed XML', function (): void {
    $path = xmlVariant('<cfdi:Comprobante><sin cerrar>');

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, 'malformed XML');
});

it('detects a credit note and its replacement', function (): void {
    $data = $this->parser->parse(cfdiFixture('cfdi_credit_note.xml'));

    expect($data->invoiceType)->toBe('E')
        ->and($data->isCreditNote())->toBeTrue()
        ->and($data->replacedUuid)->toBe('11111111-2222-3333-4444-555555555555')
        ->and($data->uuid)->toBe('99999999-8888-7777-6666-555555555555')
        ->and($data->warnings)->toContain('replaces CFDI 11111111-2222-3333-4444-555555555555 (TipoRelacion 04)');
});

it('accepts a CFDI 3.3', function (): void {
    $path = xmlVariant(str_replace(
        'http://www.sat.gob.mx/cfd/4',
        'http://www.sat.gob.mx/cfd/3',
        exampleCfdi(),
    ));

    expect($this->parser->parse($path)->issuerRfc)->toBe('AAA010101AAA');
});

it('ignores the namespace prefix', function (array $replacements): void {
    $path = xmlVariant(str_replace(['<cfdi:', '</cfdi:', 'xmlns:cfdi='], $replacements, exampleCfdi()));

    expect($this->parser->parse($path)->issuerRfc)->toBe('AAA010101AAA');
})->with([
    'a different prefix' => [['<c:', '</c:', 'xmlns:c=']],
    'no prefix' => [['<', '</', 'xmlns=']],
]);

it('rejects when a required Comprobante attribute is missing', function (string $attribute, string $reason): void {
    $path = xmlVariant(preg_replace('/\n *'.$attribute.'="[^"]*"/', '', exampleCfdi(), 1) ?? '');

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, $reason);
})->with([
    ['Fecha', 'the Fecha attribute is missing'],
    ['Total', 'the Total attribute is missing'],
]);

it('rejects when the Emisor RFC is missing', function (): void {
    $path = xmlVariant(str_replace('Rfc="AAA010101AAA" ', '', exampleCfdi()));

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, 'Emisor RFC');
});

it('rejects when the Receptor RFC is missing', function (): void {
    $path = xmlVariant(str_replace('Rfc="BBBB010101BBB" ', '', exampleCfdi()));

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, 'Receptor RFC');
});
