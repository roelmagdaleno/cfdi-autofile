<?php

use App\Cfdi\Naming\Placeholder;
use App\Support\AppSettings;
use Illuminate\Support\Facades\App;

/**
 * @param  list<array{placeholder: Placeholder|null, text: string}>  $segments
 * @return list<string>
 */
function segmentLabels(array $segments): array
{
    return array_map(
        static fn (array $segment): string => $segment['placeholder'] === null
            ? 'text:'.$segment['text']
            : 'piece:'.$segment['placeholder']->value,
        $segments,
    );
}

it('splits the default template into pieces and text', function (): void {
    $segments = Placeholder::segments(AppSettings::DEFAULT_TEMPLATE);

    expect(segmentLabels($segments))->toBe([
        'piece:date',
        'text: ',
        'piece:type',
        'text: ',
        'piece:counterparty',
        'text: - ',
        'piece:amount',
        'piece:credit_note',
    ]);
});

it('leaves an unknown placeholder as text', function (): void {
    $segments = Placeholder::segments('{date} {folio}');

    expect(segmentLabels($segments))->toBe(['piece:date', 'text: {folio}']);
});

it('does not confuse text that reads like a piece', function (): void {
    $segments = Placeholder::segments('date');

    expect(segmentLabels($segments))->toBe(['text:date']);
});

// The pill label is display-only, so it follows the active locale while the
// backing value (the token stored in the template) stays English.
it('translates the label without touching the token', function (): void {
    expect(Placeholder::Amount->label())->toBe('amount');

    App::setLocale('es');

    expect(Placeholder::Amount->label())->toBe('monto')
        ->and(Placeholder::Amount->token())->toBe('{amount}');
});
