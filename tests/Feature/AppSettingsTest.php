<?php

use App\Cfdi\Naming\TypeLetters;
use App\Models\Setting;
use App\Support\AppSettings;

beforeEach(function (): void {
    $this->settings = new AppSettings;
});

it('has no RFC by default', function (): void {
    expect($this->settings->rfc())->toBeNull();
});

it('stores the RFC uppercased and trimmed', function (): void {
    $this->settings->setRfc('  bbbb010101bbb ');

    expect($this->settings->rfc())->toBe('BBBB010101BBB');
    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('uses the default template when nothing is stored', function (): void {
    expect($this->settings->template())->toBe('{date} {type} {counterparty} - {amount}{credit_note}');
});

it('stores the template verbatim', function (): void {
    $this->settings->setTemplate('{type} {date} - {counterparty}');

    expect($this->settings->template())->toBe('{type} {date} - {counterparty}');
});

it('falls back to the default template when the stored one is empty', function (): void {
    $this->settings->setTemplate('');

    expect($this->settings->template())->toBe(AppSettings::DEFAULT_TEMPLATE);
});

it('uses the canonical letters when nothing is stored', function (): void {
    $letters = $this->settings->typeLetters();

    expect($letters->income)->toBe('I')
        ->and($letters->expense)->toBe('G');
});

it('stores the letters trimmed', function (): void {
    $this->settings->setTypeLetters(new TypeLetters(income: ' E ', expense: 'R'));

    $letters = $this->settings->typeLetters();

    expect($letters->income)->toBe('E')
        ->and($letters->expense)->toBe('R');
    $this->assertDatabaseHas('settings', ['key' => 'letter_income', 'value' => 'E']);
    $this->assertDatabaseHas('settings', ['key' => 'letter_expense', 'value' => 'R']);
});

it('falls back to the canonical letter when the stored one is blank', function (): void {
    $this->settings->setTypeLetters(new TypeLetters(income: '', expense: 'R'));

    $letters = $this->settings->typeLetters();

    expect($letters->income)->toBe('I')
        ->and($letters->expense)->toBe('R');
});

it('overwrites instead of duplicating', function (): void {
    $this->settings->setRfc('AAA010101AAA');
    $this->settings->setRfc('BBB020202BBB');

    expect($this->settings->rfc())->toBe('BBB020202BBB');
    expect(Setting::query()->where('key', 'rfc')->count())->toBe(1);
});
