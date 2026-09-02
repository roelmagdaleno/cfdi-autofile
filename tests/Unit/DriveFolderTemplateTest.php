<?php

use App\Drive\DriveFolderTemplate;

it('substitutes the year', function (): void {
    expect((new DriveFolderTemplate('{year} - Emitidas'))->render(2026))->toBe('2026 - Emitidas');
});

it('substitutes every occurrence of the token', function (): void {
    expect((new DriveFolderTemplate('{year}/{year}'))->render(2025))->toBe('2025/2025');
});

it('leaves a template without the token untouched', function (): void {
    // `isValid()` rejects this, but rendering must stay total: a saved template
    // from before a validation change should not throw.
    expect((new DriveFolderTemplate('Facturas'))->render(2026))->toBe('Facturas');
});

it('accepts a template with the year token', function (string $template): void {
    expect(DriveFolderTemplate::isValid($template))->toBeTrue();
})->with([
    'default income' => ['{year} - Emitidas'],
    'default expense' => ['{year} - Recibidas'],
    'token last' => ['Facturas {year}'],
    'token alone' => ['{year}'],
    'accents' => ['{year} · Facturación'],
]);

it('rejects a template that cannot name a folder', function (string $template): void {
    expect(DriveFolderTemplate::isValid($template))->toBeFalse();
})->with([
    'empty' => [''],
    'blank' => ['   '],
    // Without the year every year would land in the same folder, silently
    // undoing the whole point of the integration.
    'no year token' => ['Emitidas'],
    'wrong token' => ['{año} - Emitidas'],
    'slash' => ['{year}/Emitidas'],
    'backslash' => ['{year}\\Emitidas'],
    'too long' => [['{year} '.str_repeat('a', DriveFolderTemplate::MAX_LENGTH)][0]],
]);
