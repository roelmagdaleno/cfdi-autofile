<?php

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

it('overwrites instead of duplicating', function (): void {
    $this->settings->setRfc('AAA010101AAA');
    $this->settings->setRfc('BBB020202BBB');

    expect($this->settings->rfc())->toBe('BBB020202BBB');
    expect(Setting::query()->where('key', 'rfc')->count())->toBe(1);
});
