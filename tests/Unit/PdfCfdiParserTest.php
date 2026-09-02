<?php

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Parsing\PdfCfdiParser;
use Tests\Support\FakePdfTextExtractor;
use Tests\Support\ThrowingPdfTextExtractor;

/**
 * Texto base de un CFDI valido, igual al del proyecto de Python.
 */
function pdfCfdiValido(): string
{
    return implode("\n", [
        'FACTURA ELECTRONICA CFDI 4.0',
        'Emisor: SERVICIOS CONTABLES DEL NORTE SA DE CV',
        'RFC Emisor: AAA010101AAA',
        'Receptor: RECEPTOR DE PRUEBA   RFC: BBBB010101BBB',
        'Fecha de emision: 2026-04-05T10:15:00',
        'Folio Fiscal (UUID): 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Subtotal: $2,500.00',
        'IVA 16%: $400.00',
        'Total: $2,900.00',
    ]);
}

function parsePdfText(string $text): CfdiData
{
    return (new PdfCfdiParser(new FakePdfTextExtractor($text)))->parse('/no/importa.pdf');
}

it('acepta con confianza baja', function (): void {
    $data = parsePdfText(pdfCfdiValido());

    expect($data->confidence)->toBe(Confidence::Low)
        ->and($data->source)->toBe(SourceFormat::Pdf)
        ->and($data->date)->toBe('2026-04-05')
        ->and($data->total)->toBe('2900.00')
        ->and($data->uuid)->toBe('7f8e9d0c-1b2a-4c3d-8e5f-0a1b2c3d4e5f')
        ->and($data->invoiceType)->toBeNull()
        ->and($data->warnings)->not->toBeEmpty();

    expect([$data->issuerRfc, $data->recipientRfc])
        ->toEqualCanonicalizing(['AAA010101AAA', 'BBBB010101BBB']);
});

it('no confunde el Subtotal con el Total', function (): void {
    expect(parsePdfText(pdfCfdiValido())->total)->toBe('2900.00');
});

it('rechaza sin texto extraible', function (string $text): void {
    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'no tiene texto extraible');
})->with([
    'vacio' => [''],
    'solo espacios' => ["  \n\t "],
    'solo espacio duro' => ["\u{00A0}\u{00A0}"],
]);

it('rechaza con menos de dos RFCs', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Total: $100.00',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'al menos 2');
});

it('rechaza sin UUID ni folio fiscal', function (): void {
    $text = implode("\n", [
        'RECIBO SIMPLE',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Fecha: 2026-04-06',
        'Total: $150.00',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'no parece estar timbrado');
});

it('rechaza sin total', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Fecha: 2026-04-06',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'no se pudo identificar un total');
});

it('rechaza sin fecha', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Total: $150.00',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'no se pudo identificar la fecha de emision');
});

it('acepta la frase "Folio fiscal" sin UUID', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio fiscal: pendiente',
        'Fecha: 2026-04-06',
        'Total: $150.00',
    ]);

    $data = parsePdfText($text);

    expect($data->uuid)->toBeNull()
        ->and($data->total)->toBe('150.00');
});

it('reconoce un RFC que empieza con Ñ', function (): void {
    $text = str_replace('AAA010101AAA', 'ÑAA010101AAA', pdfCfdiValido());

    expect(parsePdfText($text)->issuerRfc)->toBe('ÑAA010101AAA');
});

it('convierte una fecha dd/mm/yyyy', function (): void {
    $text = str_replace('2026-04-05T10:15:00', '05/04/2026', pdfCfdiValido());

    expect(parsePdfText($text)->date)->toBe('2026-04-05');
});

it('rechaza cuando la libreria de PDF revienta', function (): void {
    $parser = new PdfCfdiParser(new ThrowingPdfTextExtractor('archivo corrupto'));

    expect(fn () => $parser->parse('/no/importa.pdf'))
        ->toThrow(CfdiRejectedException::class, 'No se pudo abrir el PDF: archivo corrupto');
});
