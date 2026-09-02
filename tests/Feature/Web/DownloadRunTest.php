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
 * Corre una corrida con un CFDI valido y uno invalido.
 */
function runWithGoodAndBad(): string
{
    return Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml'),
            fixtureUpload('no_es_cfdi.xml', 'basura.xml'),
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

it('un archivo rechazado no aparece en el ZIP', function (): void {
    $runId = runWithGoodAndBad();

    $response = $this->get(signedDownload($runId));
    $response->assertOk();

    $entries = zipEntries($response->streamedContent());

    expect($entries)->toBe(['2026-02-14 G CONTADOR - 11600.00.xml'])
        ->and($entries)->not->toContain('basura.xml')
        ->and($entries)->not->toContain('no_es_cfdi.xml');
});

it('el ZIP no incluye la carpeta de entrada', function (): void {
    $runId = runWithGoodAndBad();

    $entries = zipEntries($this->get(signedDownload($runId))->streamedContent());

    foreach ($entries as $entry) {
        expect($entry)->not->toStartWith('entrada/')
            ->and($entry)->not->toBe('factura01.xml');
    }
});

it('responde 404 si la corrida no existe', function (): void {
    $this->get(signedDownload((string) Str::uuid()))->assertNotFound();
});

it('responde 403 sin firma', function (): void {
    $runId = runWithGoodAndBad();

    $this->get("/descargar/{$runId}")->assertForbidden();
});

it('responde 409 si la corrida no termino', function (): void {
    $run = Run::factory()->create(['step' => Run::STEP_COUNTERPARTIES]);

    $this->get(signedDownload($run->id))->assertStatus(409);
});

it('responde 410 si cfdi:clean ya se llevo los archivos', function (): void {
    $runId = runWithGoodAndBad();

    $this->artisan('cfdi:clean', ['--hours' => 0])->assertExitCode(0);

    $this->get(signedDownload($runId))->assertStatus(410);
});

it('no acepta un id que no sea UUID', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with([
    'traversal' => ['/descargar/..%2F..%2Fetc%2Fpasswd'],
    'texto' => ['/descargar/corrida'],
    'numero' => ['/descargar/1'],
]);
