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

it('copies a valid CFDI and leaves the original', function (): void {
    $path = putFixture($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $result = $this->processor->process([$path], optionsFor($this->out));

    expect($result->okFiles)->toBe(1)
        ->and($result->totalFiles)->toBe(1)
        ->and($result->allOk())->toBeTrue()
        ->and($result->rows[0]->status)->toBe(FileStatus::Ok)
        ->and($result->rows[0]->note)->toBe('copied');

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and($path)->toBeFile();
});

it('moves the original with the move option', function (): void {
    $path = putFixture($this->in, 'example_cfdi.xml', 'f.xml');

    $result = $this->processor->process([$path], optionsFor($this->out, move: true));

    expect($result->rows[0]->note)->toBe('moved')
        ->and(file_exists($path))->toBeFalse()
        ->and($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('preserves the modification time when copying', function (): void {
    $path = putFixture($this->in, 'example_cfdi.xml', 'f.xml');
    touch($path, 1700000000);

    $this->processor->process([$path], optionsFor($this->out));

    expect(filemtime($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml'))->toBe(1700000000);
});

it('disambiguates repeated names', function (): void {
    $a = putFixture($this->in, 'example_cfdi.xml', 'a.xml');
    $b = putFixture($this->in, 'example_cfdi.xml', 'b.xml');

    $this->processor->process([$a, $b], optionsFor($this->out));

    $names = array_map('basename', File::files($this->out));
    sort($names);

    expect($names)->toBe([
        '2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00 (2).xml',
        '2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml',
    ]);
});

it('rejects an XML that is not a CFDI and does not copy it', function (): void {
    $path = putFixture($this->in, 'not_a_cfdi.xml', 'junk.xml');

    $result = $this->processor->process([$path], optionsFor($this->out));

    expect($result->okFiles)->toBe(0)
        ->and($result->totalFiles)->toBe(1)
        ->and($result->allOk())->toBeFalse()
        ->and($result->rows[0]->status)->toBe(FileStatus::Rejected)
        ->and($result->rows[0]->newName)->toBeNull()
        ->and($result->rows[0]->note)->toContain('not a valid CFDI');

    expect(File::files($this->out))->toBeEmpty()
        ->and($path)->toBeFile();
});

it('emits one row per file of a rejected group', function (): void {
    $xml = putFixture($this->in, 'not_a_cfdi.xml', 'pair.xml');
    $pdf = $this->in.'/pair.pdf';
    file_put_contents($pdf, 'not a pdf');
    fakePdfText('');

    $result = app(CfdiProcessor::class)->process([$xml, $pdf], optionsFor($this->out));

    expect($result->rows)->toHaveCount(2)
        ->and($result->rows[0]->original)->toBe('pair.xml')
        ->and($result->rows[1]->original)->toBe('pair.pdf')
        // The FIRST reason is kept, the XML's.
        ->and($result->rows[0]->note)->toContain('not a valid CFDI')
        ->and($result->rows[1]->note)->toContain('not a valid CFDI');

    expect(File::files($this->out))->toBeEmpty();
});

it('the PDF rescues the group when the XML is invalid', function (): void {
    $xml = putFixture($this->in, 'not_a_cfdi.xml', 'pair.xml');
    $pdf = $this->in.'/pair.pdf';
    file_put_contents($pdf, 'x');

    fakePdfText(implode("\n", [
        'RFC Emisor: AAA010101AAA',
        'RFC Receptor: BBBB010101BBB',
        'Folio Fiscal: 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F',
        'Fecha: 2026-04-06',
        'Total: $150.00',
    ]));

    $result = app(CfdiProcessor::class)->process([$xml, $pdf], optionsFor($this->out));

    // Low confidence -> Review, and both files share the name.
    expect($result->rows)->toHaveCount(2)
        ->and($result->rows[0]->status)->toBe(FileStatus::Review)
        ->and($result->rows[1]->status)->toBe(FileStatus::Review)
        ->and($result->okFiles)->toBe(2);

    expect($this->out.'/2026-04-06 G AAA010101AAA - 150.00.xml')->toBeFile()
        ->and($this->out.'/2026-04-06 G AAA010101AAA - 150.00.pdf')->toBeFile();
});

it('the PDF and the XML of the same group share the XML name', function (): void {
    $xml = putFixture($this->in, 'example_cfdi.xml', 'pair.xml');
    $pdf = $this->in.'/pair.pdf';
    file_put_contents($pdf, 'x');

    $result = $this->processor->process([$xml, $pdf], optionsFor($this->out));

    expect($result->okFiles)->toBe(2)
        ->and($result->rows[0]->status)->toBe(FileStatus::Ok);

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.pdf')->toBeFile();
});

it('warns once per file when my RFC does not appear', function (): void {
    $xml = putFixture($this->in, 'cfdi_credit_note.xml', 'pair.xml');
    $pdf = $this->in.'/pair.pdf';
    file_put_contents($pdf, 'x');

    $result = $this->processor->process([$xml, $pdf], optionsFor($this->out, 'ZZZ010101ZZZ'));

    // 2 CFDI warnings x 2 files = 4 lines.
    expect($result->warnings)->toHaveCount(4);

    foreach ($result->warnings as $warning) {
        expect($warning)->toStartWith('  ⚠ ');
    }

    expect(implode("\n", $result->warnings))
        ->toContain('your RFC ZZZ010101ZZZ does not appear as issuer or recipient')
        ->toContain('replaces CFDI 11111111-2222-3333-4444-555555555555 (TipoRelacion 04)');

    // The `?` letter is sanitized to `-` and the credit note is marked.
    expect($this->out.'/2026-03-01 - SERVICIOS CONTABLES NORTE - 1160.00 (credit note).xml')->toBeFile();
});

it('uses the cached counterparty name', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'Contador']);
    $path = putFixture($this->in, 'example_cfdi.xml', 'f.xml');

    $this->processor->process([$path], optionsFor($this->out));

    expect($this->out.'/2026-02-14 G Contador - 11600.00.xml')->toBeFile();
});

it('creates the output folder even when everything is rejected', function (): void {
    $path = putFixture($this->in, 'not_a_cfdi.xml', 'junk.xml');

    $this->processor->process([$path], optionsFor($this->out));

    expect($this->out)->toBeDirectory()
        ->and(File::files($this->out))->toBeEmpty();
});

it('returns the placed CFDI data so it can be archived', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $path = putFixture($this->in, 'example_cfdi.xml', 'invoice01.xml');

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

it('a rejected file carries no data to archive', function (): void {
    $path = putFixture($this->in, 'not_a_cfdi.xml', 'junk.xml');

    $result = $this->processor->process([$path], optionsFor($this->out));

    expect($result->rows[0]->status)->toBe(FileStatus::Rejected)
        ->and($result->rows[0]->placed)->toBeNull();
});
