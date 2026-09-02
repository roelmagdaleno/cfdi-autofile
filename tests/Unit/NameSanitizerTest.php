<?php

use App\Cfdi\Naming\NameSanitizer;

beforeEach(function (): void {
    $this->sanitizer = new NameSanitizer;
});

it('quita acentos y la ñ', function (string $input, string $expected): void {
    expect($this->sanitizer->stripAccents($input))->toBe($expected);
})->with([
    ['Café Piñón', 'Cafe Pinon'],
    ['ÑOÑO', 'NONO'],
    ['áéíóúÁÉÍÓÚ', 'aeiouAEIOU'],
    ['sin acentos', 'sin acentos'],
]);

it('reemplaza los caracteres prohibidos por guiones', function (): void {
    expect($this->sanitizer->sanitize('a/b\\c:d*e?f"g<h>i|j'))->toBe('a-b-c-d-e-f-g-h-i-j');
});

it('elimina comas y signos de peso', function (): void {
    expect($this->sanitizer->sanitize('$1,160.00'))->toBe('1160.00');
});

it('colapsa espacios en blanco', function (): void {
    expect($this->sanitizer->sanitize("  a  \t b \n c  "))->toBe('a b c');
});

it('colapsa el espacio duro', function (): void {
    expect($this->sanitizer->sanitize("a\u{00A0}\u{00A0}b"))->toBe('a b');
});

it('quita los puntos de ambos extremos', function (): void {
    expect($this->sanitizer->sanitize('...nombre...'))->toBe('nombre');
});

it('conserva los puntos interiores', function (): void {
    expect($this->sanitizer->sanitize('S.A.B de nombre'))->toBe('S.A.B de nombre');
});

it('hace trim de espacios antes que de puntos', function (): void {
    // `.strip().strip('.')` de Python deja el espacio final intacto.
    expect($this->sanitizer->sanitize('a. .'))->toBe('a. ');
});
