<?php

use App\Cfdi\Naming\NameSanitizer;

beforeEach(function (): void {
    $this->sanitizer = new NameSanitizer;
});

it('strips accents and the ñ', function (string $input, string $expected): void {
    expect($this->sanitizer->stripAccents($input))->toBe($expected);
})->with([
    ['Café Piñón', 'Cafe Pinon'],
    ['ÑOÑO', 'NONO'],
    ['áéíóúÁÉÍÓÚ', 'aeiouAEIOU'],
    ['no accents', 'no accents'],
]);

it('replaces forbidden characters with dashes', function (): void {
    expect($this->sanitizer->sanitize('a/b\\c:d*e?f"g<h>i|j'))->toBe('a-b-c-d-e-f-g-h-i-j');
});

it('removes commas and dollar signs', function (): void {
    expect($this->sanitizer->sanitize('$1,160.00'))->toBe('1160.00');
});

it('collapses whitespace', function (): void {
    expect($this->sanitizer->sanitize("  a  \t b \n c  "))->toBe('a b c');
});

it('collapses the non-breaking space', function (): void {
    expect($this->sanitizer->sanitize("a\u{00A0}\u{00A0}b"))->toBe('a b');
});

it('strips dots from both ends', function (): void {
    expect($this->sanitizer->sanitize('...name...'))->toBe('name');
});

it('keeps inner dots', function (): void {
    expect($this->sanitizer->sanitize('S.A.B of name'))->toBe('S.A.B of name');
});

it('trims spaces before dots', function (): void {
    // Python's `.strip().strip('.')` leaves the trailing space intact.
    expect($this->sanitizer->sanitize('a. .'))->toBe('a. ');
});
