<?php

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Parsing\PdfCfdiParser;
use Tests\Support\FakePdfTextExtractor;
use Tests\Support\ThrowingPdfTextExtractor;

/**
 * Base text of a valid CFDI, identical to the Python project's.
 *
 * The contents stay in Spanish: it stands in for a real invoice issued in
 * Mexico, and the parser's heuristics match against that wording.
 */
function validPdfCfdi(): string
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
    return (new PdfCfdiParser(new FakePdfTextExtractor($text)))->parse('/does/not/matter.pdf');
}

it('accepts with low confidence', function (): void {
    $data = parsePdfText(validPdfCfdi());

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

it('does not confuse the Subtotal with the Total', function (): void {
    expect(parsePdfText(validPdfCfdi())->total)->toBe('2900.00');
});

it('rejects without extractable text', function (string $text): void {
    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'no extractable text');
})->with([
    'empty' => [''],
    'only spaces' => ["  \n\t "],
    'only a non-breaking space' => ["\u{00A0}\u{00A0}"],
]);

it('rejects with fewer than two RFCs', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Total: $100.00',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'at least 2');
});

it('rejects without a UUID or a folio fiscal', function (): void {
    $text = implode("\n", [
        'RECIBO SIMPLE',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Fecha: 2026-04-06',
        'Total: $150.00',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'does not look stamped');
});

it('rejects without a total', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Fecha: 2026-04-06',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'could not identify a total');
});

it('rejects without a date', function (): void {
    $text = implode("\n", [
        'FACTURA',
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Total: $150.00',
    ]);

    expect(fn () => parsePdfText($text))
        ->toThrow(CfdiRejectedException::class, 'could not identify the issue date');
});

it('accepts the phrase "Folio fiscal" without a UUID', function (): void {
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

it('recognizes an RFC starting with Ñ', function (): void {
    $text = str_replace('AAA010101AAA', 'ÑAA010101AAA', validPdfCfdi());

    expect(parsePdfText($text)->issuerRfc)->toBe('ÑAA010101AAA');
});

it('converts a dd/mm/yyyy date', function (): void {
    $text = str_replace('2026-04-05T10:15:00', '05/04/2026', validPdfCfdi());

    expect(parsePdfText($text)->date)->toBe('2026-04-05');
});

it('rejects when the PDF library blows up', function (): void {
    $parser = new PdfCfdiParser(new ThrowingPdfTextExtractor('corrupt file'));

    expect(fn () => $parser->parse('/does/not/matter.pdf'))
        ->toThrow(CfdiRejectedException::class, 'Could not open the PDF: corrupt file');
});
