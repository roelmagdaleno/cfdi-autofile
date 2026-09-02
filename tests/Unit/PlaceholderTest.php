<?php

use App\Cfdi\Naming\Placeholder;
use App\Support\AppSettings;

/**
 * @param  list<array{placeholder: Placeholder|null, text: string}>  $segments
 * @return list<string>
 */
function segmentLabels(array $segments): array
{
    return array_map(
        static fn (array $segment): string => $segment['placeholder'] === null
            ? 'texto:'.$segment['text']
            : 'pieza:'.$segment['placeholder']->value,
        $segments,
    );
}

it('parte la plantilla default en piezas y texto', function (): void {
    $segments = Placeholder::segments(AppSettings::DEFAULT_TEMPLATE);

    expect(segmentLabels($segments))->toBe([
        'pieza:fecha',
        'texto: ',
        'pieza:tipo',
        'texto: ',
        'pieza:contraparte',
        'texto: - ',
        'pieza:monto',
        'pieza:nota_credito',
    ]);
});

it('deja como texto un placeholder desconocido', function (): void {
    $segments = Placeholder::segments('{fecha} {folio}');

    expect(segmentLabels($segments))->toBe(['pieza:fecha', 'texto: {folio}']);
});

it('no confunde un texto que se llama igual que una pieza', function (): void {
    $segments = Placeholder::segments('fecha');

    expect(segmentLabels($segments))->toBe(['texto:fecha']);
});
