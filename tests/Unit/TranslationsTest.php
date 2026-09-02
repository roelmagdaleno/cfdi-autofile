<?php

use App\Cloud\Data\CloudProvider;

/**
 * Flattens a nested translation array into dot-notation keys.
 *
 * @param  array<string, mixed>  $lines
 * @return list<string>
 */
function translationKeys(array $lines, string $prefix = ''): array
{
    $keys = [];

    foreach ($lines as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = [...$keys, ...translationKeys($value, $path)];

            continue;
        }

        $keys[] = $path;
    }

    sort($keys);

    return $keys;
}

/**
 * @return list<string>
 */
function keysOf(string $locale, string $group): array
{
    /** @var array<string, mixed> $lines */
    $lines = require base_path("lang/{$locale}/{$group}.php");

    return translationKeys($lines);
}

// A key present in `en` but missing from `es` silently falls back to English,
// which reads as a half-translated screen rather than an error. This is the only
// thing that catches it.
it('exposes the same keys in every locale', function (string $group): void {
    expect(keysOf('es', $group))->toBe(keysOf('en', $group));
})->with(['cfdi', 'console', 'ui']);

it('has no empty translation', function (string $locale, string $group): void {
    /** @var array<string, mixed> $lines */
    $lines = require base_path("lang/{$locale}/{$group}.php");

    foreach (translationKeys($lines) as $key) {
        expect(trim((string) data_get($lines, $key)))->not->toBe('', "{$locale}.{$group}.{$key} is empty");
    }
})->with(['en', 'es'])->with(['cfdi', 'console', 'ui']);

// `CloudProvider::label()` reads `ui.cloud.provider.<value>`, and every
// `:provider` placeholder in the app is filled from it. A provider added
// without its display name would render the raw key on screen, in every
// sentence at once.
it('names every cloud provider in every locale', function (string $locale): void {
    /** @var array<string, mixed> $lines */
    $lines = require base_path("lang/{$locale}/ui.php");

    foreach (CloudProvider::cases() as $provider) {
        expect(trim((string) data_get($lines, "cloud.provider.{$provider->value}")))
            ->not->toBe('', "{$locale}.ui.cloud.provider.{$provider->value} is missing");
    }
})->with(['en', 'es']);
