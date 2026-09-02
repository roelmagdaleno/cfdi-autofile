<?php

use App\Cfdi\CfdiProcessor;
use App\Cfdi\Data\FileStatus;
use App\Cfdi\Data\ProcessOptions;
use App\Cfdi\Data\TypeLetter;
use App\Models\Counterparty;
use App\Support\AppSettings;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tmp = cfdiTempDir();
    $this->in = $this->tmp.'/in';
    $this->out = $this->tmp.'/out';
    mkdir($this->in);

    $this->processor = app(CfdiProcessor::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

function optionsFor(string $out, string $rfc = 'BBBB010101BBB', bool $move = false): ProcessOptions
{
    return new ProcessOptions($rfc, AppSettings::DEFAULT_TEMPLATE, $out, $move);
}

function putFixture(string $dir, string $fixture, string $as): string
{
    $path = $dir.'/'.$as;
    copy(cfdiFixture($fixture), $path);

    return $path;
}

it('copia un CFDI valido y deja el original', function (): void {
    $path = putFixture($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');

    $result = $this->processor->process([$path], optionsFor($this->out));

    expect($result->okFiles)->toBe(1)
        ->and($result->totalFiles)->toBe(1)
        ->and($result->allOk())->toBeTrue()
        ->and($result->rows[0]->status)->toBe(FileStatus::Ok)
        ->and($result->rows[0]->note)->toBe('copiado');

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and($path)->toBeFile();
});

it('mueve el original con la opcion move', function (): void {
    $path = putFixture($this->in, 'ejemplo_cfdi.xml', 'f.xml');

    $result = $this->processor->process([$path], optionsFor($this->out, move: true));

    expect($result->rows[0]->note)->toBe('movido')
        ->and(file_exists($path))->toBeFalse()
        ->and($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('preserva la fecha de modificacion al copiar', function (): void {
    $path = putFixture($this->in, 'ejemplo_cfdi.xml', 'f.xml');
    touch($path, 1700000000);

    $this->processor->process([$path], optionsFor($this->out));

    expect(filemtime($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml'))->toBe(1700000000);
});

it('desambigua nombres repetidos', function (): void {
    $a = putFixture($this->in, 'ejemplo_cfdi.xml', 'a.xml');
    $b = putFixture($this->in, 'ejemplo_cfdi.xml', 'b.xml');

    $this->processor->process([$a, $b], optionsFor($this->out));

    $names = array_map('basename', File::files($this->out));
    sort($names);

    expect($names)->toBe([
        '2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00 (2).xml',
        '2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml',
    ]);
});

it('rechaza un XML que no es CFDI y no lo copia', function (): void {
    $path = putFixture($this->in, 'no_es_cfdi.xml', 'basura.xml');

    $result = $this->processor->process([$path], optionsFor($this->out));

    expect($result->okFiles)->toBe(0)
        ->and($result->totalFiles)->toBe(1)
        ->and($result->allOk())->toBeFalse()
        ->and($result->rows[0]->status)->toBe(FileStatus::Rejected)
        ->and($result->rows[0]->newName)->toBeNull()
        ->and($result->rows[0]->note)->toContain('no es un CFDI valido');

    expect(File::files($this->out))->toBeEmpty()
        ->and($path)->toBeFile();
});

it('emite una fila por cada archivo del grupo rechazado', function (): void {
    $xml = putFixture($this->in, 'no_es_cfdi.xml', 'par.xml');
    $pdf = $this->in.'/par.pdf';
    file_put_contents($pdf, 'no es un pdf');
    fakePdfText('');

    $result = app(CfdiProcessor::class)->process([$xml, $pdf], optionsFor($this->out));

    expect($result->rows)->toHaveCount(2)
        ->and($result->rows[0]->original)->toBe('par.xml')
        ->and($result->rows[1]->original)->toBe('par.pdf')
        // Se conserva el PRIMER motivo, el del XML.
        ->and($result->rows[0]->note)->toContain('no es un CFDI valido')
        ->and($result->rows[1]->note)->toContain('no es un CFDI valido');

    expect(File::files($this->out))->toBeEmpty();
});

it('el PDF rescata al grupo cuando el XML es invalido', function (): void {
    $xml = putFixture($this->in, 'no_es_cfdi.xml', 'par.xml');
    $pdf = $this->in.'/par.pdf';
    file_put_contents($pdf, 'x');

    fakePdfText(implode("\n", [
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Fecha: 2026-04-06',
        'Total: $150.00',
    ]));

    $result = app(CfdiProcessor::class)->process([$xml, $pdf], optionsFor($this->out));

    // Confianza baja -> REVISAR, y los dos archivos comparten el nombre.
    expect($result->rows)->toHaveCount(2)
        ->and($result->rows[0]->status)->toBe(FileStatus::Review)
        ->and($result->rows[1]->status)->toBe(FileStatus::Review)
        ->and($result->okFiles)->toBe(2);

    expect($this->out.'/2026-04-06 G AAA010101AAA - 150.00.xml')->toBeFile()
        ->and($this->out.'/2026-04-06 G AAA010101AAA - 150.00.pdf')->toBeFile();
});

it('el PDF y el XML del mismo grupo comparten el nombre del XML', function (): void {
    $xml = putFixture($this->in, 'ejemplo_cfdi.xml', 'par.xml');
    $pdf = $this->in.'/par.pdf';
    file_put_contents($pdf, 'x');

    $result = $this->processor->process([$xml, $pdf], optionsFor($this->out));

    expect($result->okFiles)->toBe(2)
        ->and($result->rows[0]->status)->toBe(FileStatus::Ok);

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.pdf')->toBeFile();
});

it('avisa una vez por archivo cuando mi RFC no aparece', function (): void {
    $xml = putFixture($this->in, 'cfdi_nota_credito.xml', 'par.xml');
    $pdf = $this->in.'/par.pdf';
    file_put_contents($pdf, 'x');

    $result = $this->processor->process([$xml, $pdf], optionsFor($this->out, 'ZZZ010101ZZZ'));

    // 2 advertencias del CFDI x 2 archivos = 4 lineas.
    expect($result->warnings)->toHaveCount(4);

    foreach ($result->warnings as $warning) {
        expect($warning)->toStartWith('  ⚠ ');
    }

    expect(implode("\n", $result->warnings))
        ->toContain('tu RFC ZZZ010101ZZZ no aparece como emisor ni receptor')
        ->toContain('sustituye al CFDI 11111111-2222-3333-4444-555555555555 (TipoRelacion 04)');

    // La letra `?` se sanitiza a `-` y se marca la nota de credito.
    expect($this->out.'/2026-03-01 - SERVICIOS CONTABLES NORTE - 1160.00 (nota de credito).xml')->toBeFile();
});

it('usa el nombre cacheado de la contraparte', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'Contador']);
    $path = putFixture($this->in, 'ejemplo_cfdi.xml', 'f.xml');

    $this->processor->process([$path], optionsFor($this->out));

    expect($this->out.'/2026-02-14 G Contador - 11600.00.xml')->toBeFile();
});

it('crea la carpeta de salida aunque todo sea rechazado', function (): void {
    $path = putFixture($this->in, 'no_es_cfdi.xml', 'basura.xml');

    $this->processor->process([$path], optionsFor($this->out));

    expect($this->out)->toBeDirectory()
        ->and(File::files($this->out))->toBeEmpty();
});

it('devuelve los datos del CFDI colocado para poder archivarlo', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $path = putFixture($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');

    $placed = $this->processor->process([$path], optionsFor($this->out))->rows[0]->placed;

    expect($placed)->not->toBeNull()
        ->and($placed->path)->toBe($this->out.'/2026-02-14 G CONTADOR - 11600.00.xml')
        ->and($placed->extension)->toBe('xml')
        ->and($placed->folio)->toBe('A1B2C3D4-E5F6-4789-ABCD-1234567890AB')
        ->and($placed->issuedOn)->toBe('2026-02-14')
        ->and($placed->total)->toBe('11600.00')
        ->and($placed->type)->toBe(TypeLetter::Expense)
        ->and($placed->counterpartyRfc)->toBe('AAA010101AAA')
        ->and($placed->counterpartyName)->toBe('CONTADOR');
});

it('un rechazado no trae datos que archivar', function (): void {
    $path = putFixture($this->in, 'no_es_cfdi.xml', 'basura.xml');

    $result = $this->processor->process([$path], optionsFor($this->out));

    expect($result->rows[0]->status)->toBe(FileStatus::Rejected)
        ->and($result->rows[0]->placed)->toBeNull();
});
