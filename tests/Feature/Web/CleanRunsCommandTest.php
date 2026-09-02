<?php

use App\Models\Run;
use App\Support\Runs\RunStorage;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(RunStorage::DISK);
    Storage::fake('local');

    $this->storage = app(RunStorage::class);
});

/**
 * Crea una corrida terminada y antigua su fila.
 */
function agedRun(int $hoursAgo): Run
{
    $storage = app(RunStorage::class);
    $runId = $storage->create();

    $storage->disk()->put("{$runId}/".RunStorage::OUTPUT.'/factura.xml', 'x');

    return Run::factory()->create([
        'id' => $runId,
        'created_at' => now()->subHours($hoursAgo),
    ]);
}

it('limpia los archivos de las corridas viejas y conserva los de las recientes', function (): void {
    $old = agedRun(48);
    $fresh = agedRun(0);

    $this->artisan('cfdi:clean')
        ->expectsOutputToContain('1 corrida(s) limpiada(s).')
        ->assertExitCode(0);

    expect($this->storage->exists($old->id))->toBeFalse()
        ->and($this->storage->exists($fresh->id))->toBeTrue();
});

it('conserva la corrida en el historial y la marca como limpiada', function (): void {
    $run = agedRun(48);

    $this->artisan('cfdi:clean')->assertExitCode(0);

    expect($run->refresh()->files_purged_at)->not->toBeNull()
        ->and($run->hasFiles())->toBeFalse();
});

it('no vuelve a limpiar una corrida ya limpiada', function (): void {
    agedRun(48);

    $this->artisan('cfdi:clean')->assertExitCode(0);

    $this->artisan('cfdi:clean')
        ->expectsOutputToContain('No hay corridas viejas que limpiar.')
        ->assertExitCode(0);
});

it('respeta --hours', function (): void {
    $run = agedRun(48);

    $this->artisan('cfdi:clean', ['--hours' => 72])
        ->expectsOutputToContain('No hay corridas viejas que limpiar.')
        ->assertExitCode(0);

    expect($this->storage->exists($run->id))->toBeTrue();
});

it('con --hours=0 limpia todo', function (): void {
    $run = agedRun(0);

    $this->artisan('cfdi:clean', ['--hours' => 0])->assertExitCode(0);

    expect($this->storage->exists($run->id))->toBeFalse();
});

it('no falla cuando no hay corridas', function (): void {
    $this->artisan('cfdi:clean')
        ->expectsOutputToContain('No hay corridas viejas que limpiar.')
        ->assertExitCode(0);
});

it('borra los temporales viejos de Livewire', function (): void {
    $disk = Storage::disk('local');
    $disk->put('livewire-tmp/viejo.xml', 'x');
    $disk->put('livewire-tmp/nuevo.xml', 'x');
    touch($disk->path('livewire-tmp/viejo.xml'), now()->subHours(48)->getTimestamp());

    $this->artisan('cfdi:clean')->assertExitCode(0);

    $disk->assertMissing('livewire-tmp/viejo.xml');
    $disk->assertExists('livewire-tmp/nuevo.xml');
});
