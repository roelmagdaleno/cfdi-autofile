<?php

use App\Cfdi\Naming\CounterpartyNameSuggester;
use App\Cfdi\Naming\CounterpartyResolver;
use App\Cfdi\Naming\NameSanitizer;
use App\Cfdi\Naming\SuggestedNamePrompt;
use App\Models\Counterparty;
use Tests\Support\FixedNamePrompt;

beforeEach(function (): void {
    $sanitizer = new NameSanitizer;
    $this->resolver = new CounterpartyResolver(new CounterpartyNameSuggester($sanitizer), $sanitizer);
});

it('returns the generic name for a generic RFC without touching the database', function (string $rfc): void {
    expect($this->resolver->resolve($rfc, 'PUBLICO EN GENERAL', new SuggestedNamePrompt))->toBe('General');

    $this->assertDatabaseCount('counterparties', 0);
})->with(['XAXX010101000', 'XEXX010101000']);

it('uses the suggestion and persists it without asking', function (): void {
    $name = $this->resolver->resolve('AAA010101AAA', 'TELEFONOS DE MEXICO SAB DE CV', new SuggestedNamePrompt);

    expect($name)->toBe('TELEFONOS MEXICO');
    $this->assertDatabaseHas('counterparties', [
        'rfc' => 'AAA010101AAA',
        'name' => 'TELEFONOS MEXICO',
        'legal_name' => 'TELEFONOS DE MEXICO SAB DE CV',
    ]);
});

it('uses the cache without asking', function (): void {
    Counterparty::factory()->create(['rfc' => 'BBB020202BBB', 'name' => 'Amazon']);

    $name = $this->resolver->resolve('BBB020202BBB', 'AMAZON MEXICO SA DE CV', FixedNamePrompt::failing());

    expect($name)->toBe('Amazon');
});

it('stores the prompt answer', function (): void {
    $prompt = new FixedNamePrompt('Contador');

    expect($this->resolver->resolve('CCC030303CCC', 'DESPACHO X SA DE CV', $prompt))->toBe('Contador');
    expect($prompt->calls)->toBe(1);
    $this->assertDatabaseHas('counterparties', ['rfc' => 'CCC030303CCC', 'name' => 'Contador']);
});

it('sanitizes the prompt answer', function (): void {
    $name = $this->resolver->resolve('DDD040404DDD', null, new FixedNamePrompt('Café/Piñón'));

    expect($name)->toBe('Cafe-Pinon');
    $this->assertDatabaseHas('counterparties', ['rfc' => 'DDD040404DDD', 'name' => 'Cafe-Pinon']);
});

it('falls back to the RFC when sanitizing empties the name', function (): void {
    expect($this->resolver->resolve('EEE050505EEE', null, new FixedNamePrompt('...')))->toBe('EEE050505EEE');
});

it('normalizes the RFC on the way in', function (): void {
    $this->resolver->resolve('  bbbb010101bbb ', 'RECEPTOR DE PRUEBA', new SuggestedNamePrompt);

    $this->assertDatabaseHas('counterparties', ['rfc' => 'BBBB010101BBB']);
});

it('persists nothing when the RFC is empty', function (): void {
    expect($this->resolver->resolve(null, 'ALGUNA EMPRESA SA DE CV', new SuggestedNamePrompt))->toBe('ALGUNA EMPRESA');

    $this->assertDatabaseCount('counterparties', 0);
});

it('reports whether an RFC is already cached', function (): void {
    Counterparty::factory()->create(['rfc' => 'FFF060606FFF', 'name' => 'Conocida']);

    expect($this->resolver->isKnown('fff060606fff'))->toBeTrue()
        ->and($this->resolver->isKnown('GGG070707GGG'))->toBeFalse()
        ->and($this->resolver->isKnown(''))->toBeFalse();
});
