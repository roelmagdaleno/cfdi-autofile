<?php

use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Parsing\XmlCfdiParser;

/**
 * Escribe un XML derivado del fixture en un archivo temporal y devuelve su ruta.
 */
function xmlVariant(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'cfdi').'.xml';
    file_put_contents($path, $contents);

    return $path;
}

function ejemploCfdi(): string
{
    return (string) file_get_contents(cfdiFixture('ejemplo_cfdi.xml'));
}

beforeEach(function (): void {
    $this->parser = new XmlCfdiParser;
});

it('extrae todos los campos de un CFDI 4.0', function (): void {
    $data = $this->parser->parse(cfdiFixture('ejemplo_cfdi.xml'));

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

it('rechaza un XML que no es CFDI', function (): void {
    expect(fn () => $this->parser->parse(cfdiFixture('no_es_cfdi.xml')))
        ->toThrow(CfdiRejectedException::class, 'no es un CFDI valido');
});

it('rechaza un XML mal formado', function (): void {
    $path = xmlVariant('<cfdi:Comprobante><sin cerrar>');

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, 'XML mal formado');
});

it('detecta nota de credito y sustitucion', function (): void {
    $data = $this->parser->parse(cfdiFixture('cfdi_nota_credito.xml'));

    expect($data->invoiceType)->toBe('E')
        ->and($data->isCreditNote())->toBeTrue()
        ->and($data->replacedUuid)->toBe('11111111-2222-3333-4444-555555555555')
        ->and($data->uuid)->toBe('99999999-8888-7777-6666-555555555555')
        ->and($data->warnings)->toContain('sustituye al CFDI 11111111-2222-3333-4444-555555555555 (TipoRelacion 04)');
});

it('acepta un CFDI 3.3', function (): void {
    $path = xmlVariant(str_replace(
        'http://www.sat.gob.mx/cfd/4',
        'http://www.sat.gob.mx/cfd/3',
        ejemploCfdi(),
    ));

    expect($this->parser->parse($path)->issuerRfc)->toBe('AAA010101AAA');
});

it('ignora el prefijo del namespace', function (array $replacements): void {
    $path = xmlVariant(str_replace(['<cfdi:', '</cfdi:', 'xmlns:cfdi='], $replacements, ejemploCfdi()));

    expect($this->parser->parse($path)->issuerRfc)->toBe('AAA010101AAA');
})->with([
    'prefijo distinto' => [['<c:', '</c:', 'xmlns:c=']],
    'sin prefijo' => [['<', '</', 'xmlns=']],
]);

it('rechaza si falta un atributo obligatorio del Comprobante', function (string $attribute, string $reason): void {
    $path = xmlVariant(preg_replace('/\n *'.$attribute.'="[^"]*"/', '', ejemploCfdi(), 1) ?? '');

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, $reason);
})->with([
    ['Fecha', 'falta el atributo Fecha'],
    ['Total', 'falta el atributo Total'],
]);

it('rechaza si falta el RFC del Emisor', function (): void {
    $path = xmlVariant(str_replace('Rfc="AAA010101AAA" ', '', ejemploCfdi()));

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, 'RFC del Emisor');
});

it('rechaza si falta el RFC del Receptor', function (): void {
    $path = xmlVariant(str_replace('Rfc="BBBB010101BBB" ', '', ejemploCfdi()));

    expect(fn () => $this->parser->parse($path))
        ->toThrow(CfdiRejectedException::class, 'RFC del Receptor');
});
