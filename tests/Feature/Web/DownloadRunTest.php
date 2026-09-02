<?php

use App\Livewire\RunCfdis;
use App\Models\Counterparty;
use App\Models\Run;
use App\Support\AppSettings;
use App\Support\Runs\RunStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(RunStorage::DISK);
    Storage::fake('local');

    app(AppSettings::class)->setRfc('BBBB010101BBB');
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
});

function signedDownload(string $runId): string
{
    return URL::temporarySignedRoute('runs.download', now()->addHour(), ['run' => $runId]);
}

/**
 * Runs a run with one valid CFDI and one invalid one.
 */
function runWithGoodAndBad(): string
{
    return Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('example_cfdi.xml', 'invoice01.xml'),
            fixtureUpload('not_a_cfdi.xml', 'junk.xml'),
        ])
        ->call('analyze')
        ->get('runId');
}

/**
 * @return list<string>
 */
function zipEntries(string $body): array
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($path, $body);

    $archive = new ZipArchive;
    expect($archive->open($path))->toBeTrue();

    $entries = [];

    for ($i = 0; $i < $archive->numFiles; $i++) {
        $entries[] = (string) $archive->getNameIndex($i);
    }

    $archive->close();
    unlink($path);
    sort($entries);

    return $entries;
}

it('a rejected file does not appear in the ZIP', function (): void {
    $runId = runWithGoodAndBad();

    $response = $this->get(signedDownload($runId));
    $response->assertOk();

    $entries = zipEntries($response->streamedContent());

    expect($entries)->toBe(['2026-02-14 G CONTADOR - 11600.00.xml'])
        ->and($entries)->not->toContain('junk.xml')
        ->and($entries)->not->toContain('not_a_cfdi.xml');
});

it('the ZIP does not include the input folder', function (): void {
    $runId = runWithGoodAndBad();

    $entries = zipEntries($this->get(signedDownload($runId))->streamedContent());

    foreach ($entries as $entry) {
        expect($entry)->not->toStartWith(RunStorage::INPUT.'/')
            ->and($entry)->not->toBe('invoice01.xml');
    }
});

it('responds 404 when the run does not exist', function (): void {
    $this->get(signedDownload((string) Str::uuid()))->assertNotFound();
});

it('responds 403 without a signature', function (): void {
    $runId = runWithGoodAndBad();

    $this->get("/download/{$runId}")->assertForbidden();
});

it('responds 409 when the run has not finished', function (): void {
    $run = Run::factory()->create(['step' => Run::STEP_COUNTERPARTIES]);

    $this->get(signedDownload($run->id))->assertStatus(409);
});

it('responds 410 when cfdi:clean already took the files', function (): void {
    $runId = runWithGoodAndBad();

    $this->artisan('cfdi:clean', ['--hours' => 0])->assertExitCode(0);

    $this->get(signedDownload($runId))->assertStatus(410);
});

it('does not accept an id that is not a UUID', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with([
    'traversal' => ['/download/..%2F..%2Fetc%2Fpasswd'],
    'plain text' => ['/download/run'],
    'a number' => ['/download/1'],
]);
