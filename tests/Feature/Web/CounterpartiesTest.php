<?php

use App\Livewire\ManageCounterparties;
use App\Models\Counterparty;
use Livewire\Livewire;

it('lista las contrapartes', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    Counterparty::factory()->create(['rfc' => 'BBB020202BBB', 'name' => 'AMAZON']);

    Livewire::test(ManageCounterparties::class)
        ->assertSee('AAA010101AAA')
        ->assertSee('CONTADOR')
        ->assertSee('BBB020202BBB')
        ->assertSee('AMAZON');
});

it('muestra un estado vacio', function (): void {
    Livewire::test(ManageCounterparties::class)
        ->assertSee('Aún no hay contrapartes');
});

it('filtra por busqueda', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    Counterparty::factory()->create(['rfc' => 'BBB020202BBB', 'name' => 'AMAZON']);

    Livewire::test(ManageCounterparties::class)
        ->set('search', 'AAA')
        ->assertSee('CONTADOR')
        ->assertDontSee('AMAZON');
});

it('renombra y sanitiza el nombre', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'VIEJO']);

    Livewire::test(ManageCounterparties::class)
        ->call('startEdit', $counterparty->id)
        ->assertSet('editingName', 'VIEJO')
        ->set('editingName', 'Café/Piñón')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('counterparties', ['rfc' => 'AAA010101AAA', 'name' => 'Cafe-Pinon']);
});

it('exige un nombre al renombrar', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'VIEJO']);

    Livewire::test(ManageCounterparties::class)
        ->call('startEdit', $counterparty->id)
        ->set('editingName', '')
        ->call('saveEdit')
        ->assertHasErrors('editingName');

    $this->assertDatabaseHas('counterparties', ['name' => 'VIEJO']);
});

it('cae en el RFC si el nombre queda vacio al sanitizar', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'VIEJO']);

    Livewire::test(ManageCounterparties::class)
        ->call('startEdit', $counterparty->id)
        ->set('editingName', '...')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('counterparties', ['rfc' => 'AAA010101AAA', 'name' => 'AAA010101AAA']);
});

it('borra una contraparte', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA']);

    Livewire::test(ManageCounterparties::class)
        ->call('confirmDelete', $counterparty->id)
        ->assertSet('deletingId', $counterparty->id)
        ->call('delete');

    $this->assertDatabaseMissing('counterparties', ['id' => $counterparty->id]);
});
