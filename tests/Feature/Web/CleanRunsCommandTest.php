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
 * Creates a finished run and backdates its row.
 */
function agedRun(int $hoursAgo): Run
{
    $storage = app(RunStorage::class);
    $runId = $storage->create();

    $storage->disk()->put("{$runId}/".RunStorage::OUTPUT.'/invoice.xml', 'x');

    return Run::factory()->create([
        'id' => $runId,
        'created_at' => now()->subHours($hoursAgo),
    ]);
}

it('cleans the files of old runs and keeps the recent ones', function (): void {
    $old = agedRun(48);
    $fresh = agedRun(0);

    $this->artisan('cfdi:clean')
        ->expectsOutputToContain('1 run(s) cleaned.')
        ->assertExitCode(0);

    expect($this->storage->exists($old->id))->toBeFalse()
        ->and($this->storage->exists($fresh->id))->toBeTrue();
});

it('keeps the run in the history and marks it as cleaned', function (): void {
    $run = agedRun(48);

    $this->artisan('cfdi:clean')->assertExitCode(0);

    expect($run->refresh()->files_purged_at)->not->toBeNull()
        ->and($run->hasFiles())->toBeFalse();
});

it('does not clean an already cleaned run again', function (): void {
    agedRun(48);

    $this->artisan('cfdi:clean')->assertExitCode(0);

    $this->artisan('cfdi:clean')
        ->expectsOutputToContain('There are no old runs to clean.')
        ->assertExitCode(0);
});

it('respects --hours', function (): void {
    $run = agedRun(48);

    $this->artisan('cfdi:clean', ['--hours' => 72])
        ->expectsOutputToContain('There are no old runs to clean.')
        ->assertExitCode(0);

    expect($this->storage->exists($run->id))->toBeTrue();
});

it('with --hours=0 it cleans everything', function (): void {
    $run = agedRun(0);

    $this->artisan('cfdi:clean', ['--hours' => 0])->assertExitCode(0);

    expect($this->storage->exists($run->id))->toBeFalse();
});

it('does not fail when there are no runs', function (): void {
    $this->artisan('cfdi:clean')
        ->expectsOutputToContain('There are no old runs to clean.')
        ->assertExitCode(0);
});

it('deletes the old Livewire temporaries', function (): void {
    $disk = Storage::disk('local');
    $disk->put('livewire-tmp/old.xml', 'x');
    $disk->put('livewire-tmp/new.xml', 'x');
    touch($disk->path('livewire-tmp/old.xml'), now()->subHours(48)->getTimestamp());

    $this->artisan('cfdi:clean')->assertExitCode(0);

    $disk->assertMissing('livewire-tmp/old.xml');
    $disk->assertExists('livewire-tmp/new.xml');
});
