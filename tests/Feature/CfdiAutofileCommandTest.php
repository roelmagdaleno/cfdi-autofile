<?php

use App\Cfdi\Naming\TypeLetters;
use App\Models\Document;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->cwd = (string) getcwd();
    $this->tmp = cfdiTempDir();
    $this->in = $this->tmp.'/in';
    $this->out = $this->tmp.'/out';
    mkdir($this->in);
});

afterEach(function (): void {
    chdir($this->cwd);
    File::deleteDirectory($this->tmp);
});

function stage(string $dir, string $fixture, string $as): string
{
    $path = $dir.'/'.$as;
    copy(cfdiFixture($fixture), $path);

    return $path;
}

/**
 * Runs the command and returns the exit code along with the full text.
 *
 * `expectsOutputToContain` is not used for the table contents: that API requires
 * each substring to land on a different `doWrite` call, and a table row is a
 * single write, so two assertions about the same row overwrite each other.
 *
 * @param  array<string, mixed>  $arguments
 * @return array{code: int, output: string}
 */
function runAutofile(array $arguments): array
{
    $code = Artisan::call('cfdi:autofile', $arguments);

    return ['code' => $code, 'output' => Artisan::output()];
}

it('copies a valid CFDI and leaves the original', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('1/1 processed successfully.')
        ->assertExitCode(0);

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and($this->in.'/invoice01.xml')->toBeFile();
});

it('rejects an XML that is not a CFDI and does not copy it', function (): void {
    stage($this->in, 'not_a_cfdi.xml', 'junk.xml');

    $run = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(2)
        ->and($run['output'])->toContain('REJECTED')
        ->toContain('not a valid CFDI')
        ->toContain('0/1 processed successfully.');

    expect(File::files($this->out))->toBeEmpty()
        ->and($this->in.'/junk.xml')->toBeFile();
});

it('with --move it takes the original out of the input folder', function (): void {
    stage($this->in, 'example_cfdi.xml', 'f.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--move' => true,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect(file_exists($this->in.'/f.xml'))->toBeFalse();
});

it('disambiguates repeated names', function (): void {
    stage($this->in, 'example_cfdi.xml', 'a.xml');
    stage($this->in, 'example_cfdi.xml', 'b.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    $names = array_map('basename', File::files($this->out));
    sort($names);

    expect($names)->toBe([
        '2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00 (2).xml',
        '2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml',
    ]);
});

it('--set-rfc stores the RFC uppercased', function (): void {
    $this->artisan('cfdi:autofile', ['--set-rfc' => 'bbbb010101bbb'])
        ->expectsOutputToContain('Saved RFC: BBBB010101BBB')
        ->assertExitCode(0);

    expect(app(AppSettings::class)->rfc())->toBe('BBBB010101BBB');
    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('--set-template persists the template', function (): void {
    $this->artisan('cfdi:autofile', ['--set-template' => '{type} {date} - {counterparty}'])
        ->expectsOutputToContain('Saved template: {type} {date} - {counterparty}')
        ->assertExitCode(0);

    expect(app(AppSettings::class)->template())->toBe('{type} {date} - {counterparty}');
});

it('--set-letter-expense persists the label and leaves the other one alone', function (): void {
    $this->artisan('cfdi:autofile', ['--set-letter-expense' => 'R'])
        ->expectsOutputToContain('Saved labels: I for income, R for expenses.')
        ->assertExitCode(0);

    $letters = app(AppSettings::class)->typeLetters();

    expect($letters->expense)->toBe('R')
        ->and($letters->income)->toBe('I');
});

it('--set-letter-income rejects a label that cannot be used', function (): void {
    $this->artisan('cfdi:autofile', ['--set-letter-income' => 'a/b'])
        ->assertExitCode(1);

    $this->assertDatabaseMissing('settings', ['key' => 'letter_income']);
});

it('uses the stored labels when no flag is passed', function (): void {
    app(AppSettings::class)->setTypeLetters(new TypeLetters(income: 'E', expense: 'R'));
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect($this->out.'/2026-02-14 R SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('--letter-expense applies for this run without saving it', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--letter-expense' => 'REC',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect($this->out.'/2026-02-14 REC SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and(app(AppSettings::class)->typeLetters()->expense)->toBe('G');
});

it('rejects an unusable --letter-expense before processing anything', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--letter-expense' => 'Recibidas',
        '--no-interactive' => true,
    ])->assertExitCode(1);

    expect($this->out)->not->toBeDirectory();
});

it('without --output it uses the Renamed folder', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');
    chdir($this->tmp);

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect($this->tmp.'/Renamed')->toBeDirectory()
        ->and($this->tmp.'/Renamed/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('prints the table with the four headers and both files', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');
    stage($this->in, 'not_a_cfdi.xml', 'junk.xml');

    $run = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(2)
        ->and($run['output'])->toContain('Status')
        ->toContain('Original')
        ->toContain('New name')
        ->toContain('Note')
        ->toContain('invoice01.xml')
        ->toContain('junk.xml');
});

it('with no files and no --dir it prints the help and exits with 1', function (): void {
    $this->artisan('cfdi:autofile')->assertExitCode(1);
});

it('with no RFC and no interaction it exits with 1', function (): void {
    stage($this->in, 'example_cfdi.xml', 'f.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('Error: no RFC is configured. Use --set-rfc or --rfc.')
        ->assertExitCode(1);
});

it('uses the stored RFC when --rfc is not passed', function (): void {
    app(AppSettings::class)->setRfc('BBBB010101BBB');
    stage($this->in, 'example_cfdi.xml', 'f.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('a file rejected during collection does not count towards the total', function (): void {
    // Real behavior of the original: `total` only counts the collected files, so
    // a rejected row can coexist with exit code 0.
    stage($this->in, 'example_cfdi.xml', 'bueno.xml');

    $run = runAutofile([
        'files' => [$this->tmp.'/does-not-exist.xml'],
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(0)
        ->and($run['output'])->toContain('does not exist.')
        ->toContain('REJECTED')
        ->toContain('1/1 processed successfully.');
});

it('does not create the output folder when there is nothing to process', function (): void {
    $bad = $this->tmp.'/notes.txt';
    file_put_contents($bad, 'hello');

    $run = runAutofile([
        'files' => [$bad],
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(1)
        ->and($run['output'])->toContain('unsupported extension (only .pdf and .xml).')
        ->toContain('There are no PDF/XML files to process.')
        ->not->toContain('Output:');

    expect(is_dir($this->out))->toBeFalse();
});

it('reports a folder passed as a single file', function (): void {
    $this->artisan('cfdi:autofile', [
        'files' => [$this->in],
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('is a folder (use --dir).')
        ->assertExitCode(1);
});

it('reports a --dir that is not a folder', function (): void {
    $this->artisan('cfdi:autofile', [
        '--dir' => $this->tmp.'/nope',
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('is not a folder.')
        ->assertExitCode(1);
});

it('prints the warnings once per file', function (): void {
    stage($this->in, 'cfdi_credit_note.xml', 'pair.xml');
    file_put_contents($this->in.'/pair.pdf', 'x');

    $run = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'ZZZ010101ZZZ',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(0)
        ->and($run['output'])->toContain('Warnings:')
        ->toContain('  ⚠ pair.xml: your RFC ZZZ010101ZZZ does not appear as issuer or recipient')
        ->toContain('  ⚠ pair.pdf: your RFC ZZZ010101ZZZ does not appear as issuer or recipient')
        ->toContain('2/2 processed successfully.');

    expect($this->out.'/2026-03-01 - SERVICIOS CONTABLES NORTE - 1160.00 (credit note).xml')->toBeFile();
});

it('indexes what was processed without copying it into the app archive', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $result = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($result['code'])->toBe(0);

    $document = Document::query()->sole();

    expect($document->disk)->toBeNull()
        ->and($document->path)->toBe($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')
        ->and($document->folio)->toBe('A1B2C3D4-E5F6-4789-ABCD-1234567890AB')
        ->and($document->counterparty->rfc)->toBe('AAA010101AAA');

    expect(Storage::disk(Document::DISK)->allFiles())->toBe([]);
});

it('the table still shows the copied note', function (): void {
    stage($this->in, 'example_cfdi.xml', 'invoice01.xml');

    $result = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($result['output'])->toContain('copied');
});

it('does not index rejected files', function (): void {
    stage($this->in, 'not_a_cfdi.xml', 'junk.xml');

    runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect(Document::query()->count())->toBe(0);
});
