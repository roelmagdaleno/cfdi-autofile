<?php

use App\Cfdi\Naming\CounterpartyNameSuggester;
use App\Cfdi\Naming\NameSanitizer;

beforeEach(function (): void {
    $this->suggester = new CounterpartyNameSuggester(new NameSanitizer);
});

it('strips the legal form and caps at three words', function (?string $input, string $expected): void {
    expect($this->suggester->suggest($input))->toBe($expected);
})->with([
    ['SERVICIOS CONTABLES DEL NORTE SA DE CV', 'SERVICIOS CONTABLES NORTE'],
    ['Café Piñón S.A. de C.V.', 'Cafe Pinon'],
    ['TELEFONOS DE MEXICO SAB DE CV', 'TELEFONOS MEXICO'],
    ['UNA RAZON SOCIAL BASTANTE LARGA DE VERDAD', 'UNA RAZON SOCIAL'],
    [null, ''],
    ['', ''],
]);

it('uses every word when they were all legal forms', function (): void {
    expect($this->suggester->suggest('SA DE CV'))->toBe('SA DE CV');
});
