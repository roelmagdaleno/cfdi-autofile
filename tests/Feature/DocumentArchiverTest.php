<?php

use App\Cfdi\CfdiProcessor;
use App\Cfdi\Data\ProcessOptions;
use App\Cfdi\Data\ProcessResult;
use App\Models\Counterparty;
use App\Models\Document;
use App\Support\AppSettings;
use App\Support\Documents\DocumentArchiver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->tmp = cfdiTempDir();
    $this->in = $this->tmp.'/in';
    $this->out = $this->tmp.'/out';
    mkdir($this->in);

    $this->archiver = app(DocumentArchiver::class);

    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

/**
 * Procesa un fixture y devuelve el resultado listo para archivar.
 */
function processed(string $in, string $out, string $fixture = 'ejemplo_cfdi.xml', string $as = 'factura01.xml'): ProcessResult
{
    copy(cfdiFixture($fixture), $in.'/'.$as);

    return app(CfdiProcessor::class)->process(
        [$in.'/'.$as],
        new ProcessOptions('BBBB010101BBB', AppSettings::DEFAULT_TEMPLATE, $out),
    );
}

it('guarda una copia bajo el RFC y el anio de la contraparte', function (): void {
    $this->archiver->archive(processed($this->in, $this->out), copyIntoArchive: true);

    Storage::disk(Document::DISK)
        ->assertExists('AAA010101AAA/2026/2026-02-14 G CONTADOR - 11600.00.xml');
});

it('indexa el CFDI con los datos del comprobante', function (): void {
    $this->archiver->archive(processed($this->in, $this->out), copyIntoArchive: true);

    $document = Document::query()->sole();

    expect($document->folio)->toBe('A1B2C3D4-E5F6-4789-ABCD-1234567890AB')
        ->and($document->extension)->toBe('xml')
        ->and($document->name)->toBe('2026-02-14 G CONTADOR - 11600.00.xml')
        ->and($document->original_name)->toBe('factura01.xml')
        ->and($document->issued_on->format('Y-m-d'))->toBe('2026-02-14')
        ->and($document->total)->toBe('11600.00')
        ->and($document->type_letter)->toBe('G')
        ->and($document->disk)->toBe(Document::DISK)
        ->and($document->counterparty->rfc)->toBe('AAA010101AAA');
});

it('no copia nada y apunta a la ruta del comando', function (): void {
    $result = processed($this->in, $this->out);

    $this->archiver->archive($result, copyIntoArchive: false);

    $document = Document::query()->sole();

    expect($document->disk)->toBeNull()
        ->and($document->path)->toBe($this->out.'/2026-02-14 G CONTADOR - 11600.00.xml')
        ->and($document->fileExists())->toBeTrue();

    expect(Storage::disk(Document::DISK)->allFiles())->toBe([]);
});

it('reprocesar el mismo folio actualiza la fila en vez de duplicarla', function (): void {
    $this->archiver->archive(processed($this->in, $this->out), copyIntoArchive: true);

    File::deleteDirectory($this->out);
    $this->archiver->archive(processed($this->in, $this->out, as: 'otra-copia.xml'), copyIntoArchive: true);

    expect(Document::query()->count())->toBe(1)
        ->and(Document::query()->sole()->original_name)->toBe('otra-copia.xml')
        ->and(Storage::disk(Document::DISK)->allFiles())
        ->toBe(['AAA010101AAA/2026/2026-02-14 G CONTADOR - 11600.00.xml']);
});

it('borra el archivo viejo cuando cambia el nombre de la contraparte', function (): void {
    $this->archiver->archive(processed($this->in, $this->out), copyIntoArchive: true);

    Counterparty::query()->where('rfc', 'AAA010101AAA')->update(['name' => 'CONTADOR NUEVO']);
    File::deleteDirectory($this->out);

    $this->archiver->archive(processed($this->in, $this->out), copyIntoArchive: true);

    expect(Storage::disk(Document::DISK)->allFiles())
        ->toBe(['AAA010101AAA/2026/2026-02-14 G CONTADOR NUEVO - 11600.00.xml']);
});

it('no archiva los rechazados', function (): void {
    $result = processed($this->in, $this->out, 'no_es_cfdi.xml', 'basura.xml');

    $this->archiver->archive($result, copyIntoArchive: true);

    expect(Document::query()->count())->toBe(0)
        ->and(Storage::disk(Document::DISK)->allFiles())->toBe([]);
});

it('deja sin contraparte a un RFC generico', function (): void {
    $result = processed($this->in, $this->out, 'cfdi_rfc_generico.xml', 'publico.xml');

    $this->archiver->archive($result, copyIntoArchive: true);

    expect(Document::query()->sole()->counterparty_id)->toBeNull();
});
