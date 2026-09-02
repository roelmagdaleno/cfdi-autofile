<?php

use App\Livewire\ManageCounterparties;
use App\Models\Counterparty;
use Livewire\Livewire;

it('lists the counterparties', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    Counterparty::factory()->create(['rfc' => 'BBB020202BBB', 'name' => 'AMAZON']);

    Livewire::test(ManageCounterparties::class)
        ->assertSee('AAA010101AAA')
        ->assertSee('CONTADOR')
        ->assertSee('BBB020202BBB')
        ->assertSee('AMAZON');
});

it('shows an empty state', function (): void {
    Livewire::test(ManageCounterparties::class)
        ->assertSee('There are no counterparties yet');
});

it('filters by search', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    Counterparty::factory()->create(['rfc' => 'BBB020202BBB', 'name' => 'AMAZON']);

    Livewire::test(ManageCounterparties::class)
        ->set('search', 'AAA')
        ->assertSee('CONTADOR')
        ->assertDontSee('AMAZON');
});

it('renames and sanitizes the name', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'OLD']);

    Livewire::test(ManageCounterparties::class)
        ->call('startEdit', $counterparty->id)
        ->assertSet('editingName', 'OLD')
        ->set('editingName', 'Café/Piñón')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('counterparties', ['rfc' => 'AAA010101AAA', 'name' => 'Cafe-Pinon']);
});

it('requires a name when renaming', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'OLD']);

    Livewire::test(ManageCounterparties::class)
        ->call('startEdit', $counterparty->id)
        ->set('editingName', '')
        ->call('saveEdit')
        ->assertHasErrors('editingName');

    $this->assertDatabaseHas('counterparties', ['name' => 'OLD']);
});

it('falls back to the RFC when sanitizing empties the name', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'OLD']);

    Livewire::test(ManageCounterparties::class)
        ->call('startEdit', $counterparty->id)
        ->set('editingName', '...')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('counterparties', ['rfc' => 'AAA010101AAA', 'name' => 'AAA010101AAA']);
});

it('deletes a counterparty', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA']);

    Livewire::test(ManageCounterparties::class)
        ->call('confirmDelete', $counterparty->id)
        ->assertSet('deletingId', $counterparty->id)
        ->call('delete');

    $this->assertDatabaseMissing('counterparties', ['id' => $counterparty->id]);
});
