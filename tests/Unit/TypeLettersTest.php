<?php

use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\TypeLetters;

it('defaults to the canonical letters', function (): void {
    $letters = TypeLetters::defaults();

    expect($letters->income)->toBe('I')
        ->and($letters->expense)->toBe('G');
});

it('maps each type to its configured label', function (): void {
    $letters = new TypeLetters(income: 'E', expense: 'R');

    expect($letters->for(TypeLetter::Income))->toBe('E')
        ->and($letters->for(TypeLetter::Expense))->toBe('R');
});

// `Unknown` is the "your RFC is not on this invoice" case and stays out of the
// configuration on purpose.
it('leaves the unknown type at its question mark', function (): void {
    $letters = new TypeLetters(income: 'E', expense: 'R');

    expect($letters->for(TypeLetter::Unknown))->toBe('?');
});

it('accepts a label of one to eight characters', function (string $letter): void {
    expect(TypeLetters::isValid($letter))->toBeTrue();
})->with([
    ['I'],
    ['R'],
    ['EMI'],
    ['Recibida'],
    [' E '],
]);

it('rejects a label that is empty or too long', function (string $letter): void {
    expect(TypeLetters::isValid($letter))->toBeFalse();
})->with([
    [''],
    ['   '],
    ['Recibidas'],
]);

// These are exactly the characters `NameSanitizer` turns into a dash: accepting
// them would write something other than what was typed.
it('rejects a label with a character the sanitizer would replace', function (string $letter): void {
    expect(TypeLetters::isValid($letter))->toBeFalse();
})->with([
    ['a/b'],
    ['a\\b'],
    ['a:b'],
    ['a*b'],
    ['a?b'],
    ['a"b'],
    ['a<b'],
    ['a>b'],
    ['a|b'],
]);
