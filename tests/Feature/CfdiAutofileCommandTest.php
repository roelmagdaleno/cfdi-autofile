<?php

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
 * Corre el comando y devuelve el codigo de salida junto con el texto completo.
 *
 * No se usa `expectsOutputToContain` para el contenido de la tabla: esa API
 * exige que cada substring caiga en una llamada distinta a `doWrite`, y una fila
 * de la tabla es una sola escritura, asi que dos aserciones sobre la misma fila
 * se pisan entre si.
 *
 * @param  array<string, mixed>  $arguments
 * @return array{code: int, output: string}
 */
function runAutofile(array $arguments): array
{
    $code = Artisan::call('cfdi:autofile', $arguments);

    return ['code' => $code, 'output' => Artisan::output()];
}

it('copia un CFDI valido y deja el original', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('1/1 procesados correctamente.')
        ->assertExitCode(0);

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile()
        ->and($this->in.'/factura01.xml')->toBeFile();
});

it('rechaza un XML que no es CFDI y no lo copia', function (): void {
    stage($this->in, 'no_es_cfdi.xml', 'basura.xml');

    $run = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(2)
        ->and($run['output'])->toContain('RECHAZADO')
        ->toContain('no es un CFDI valido')
        ->toContain('0/1 procesados correctamente.');

    expect(File::files($this->out))->toBeEmpty()
        ->and($this->in.'/basura.xml')->toBeFile();
});

it('con --move saca el original de la carpeta de entrada', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'f.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--move' => true,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect(file_exists($this->in.'/f.xml'))->toBeFalse();
});

it('desambigua nombres repetidos', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'a.xml');
    stage($this->in, 'ejemplo_cfdi.xml', 'b.xml');

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

it('--set-rfc guarda el RFC en mayusculas', function (): void {
    $this->artisan('cfdi:autofile', ['--set-rfc' => 'bbbb010101bbb'])
        ->expectsOutputToContain('RFC guardado: BBBB010101BBB')
        ->assertExitCode(0);

    expect(app(AppSettings::class)->rfc())->toBe('BBBB010101BBB');
    $this->assertDatabaseHas('settings', ['key' => 'rfc', 'value' => 'BBBB010101BBB']);
});

it('--set-template persiste la plantilla', function (): void {
    $this->artisan('cfdi:autofile', ['--set-template' => '{tipo} {fecha} - {contraparte}'])
        ->expectsOutputToContain('Plantilla guardada: {tipo} {fecha} - {contraparte}')
        ->assertExitCode(0);

    expect(app(AppSettings::class)->template())->toBe('{tipo} {fecha} - {contraparte}');
});

it('sin --output usa la carpeta Renombradas', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');
    chdir($this->tmp);

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect($this->tmp.'/Renombradas')->toBeDirectory()
        ->and($this->tmp.'/Renombradas/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('imprime la tabla con los cuatro encabezados y los dos archivos', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');
    stage($this->in, 'no_es_cfdi.xml', 'basura.xml');

    $run = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(2)
        ->and($run['output'])->toContain('Estado')
        ->toContain('Original')
        ->toContain('Nuevo nombre')
        ->toContain('Nota')
        ->toContain('factura01.xml')
        ->toContain('basura.xml');
});

it('sin archivos ni --dir imprime la ayuda y sale con 1', function (): void {
    $this->artisan('cfdi:autofile')->assertExitCode(1);
});

it('sin RFC y sin interaccion sale con 1', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'f.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('Error: no hay RFC configurado. Usa --set-rfc o --rfc.')
        ->assertExitCode(1);
});

it('usa el RFC guardado cuando no se pasa --rfc', function (): void {
    app(AppSettings::class)->setRfc('BBBB010101BBB');
    stage($this->in, 'ejemplo_cfdi.xml', 'f.xml');

    $this->artisan('cfdi:autofile', [
        '--dir' => $this->in,
        '--output' => $this->out,
        '--no-interactive' => true,
    ])->assertExitCode(0);

    expect($this->out.'/2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml')->toBeFile();
});

it('un archivo rechazado en la recoleccion no cuenta en el total', function (): void {
    // Comportamiento real del original: `total` solo cuenta los recolectados,
    // asi que una fila RECHAZADO puede convivir con codigo de salida 0.
    stage($this->in, 'ejemplo_cfdi.xml', 'bueno.xml');

    $run = runAutofile([
        'files' => [$this->tmp.'/no-existe.xml'],
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(0)
        ->and($run['output'])->toContain('no existe.')
        ->toContain('RECHAZADO')
        ->toContain('1/1 procesados correctamente.');
});

it('no crea la carpeta de salida cuando no hay nada que procesar', function (): void {
    $bad = $this->tmp.'/notas.txt';
    file_put_contents($bad, 'hola');

    $run = runAutofile([
        'files' => [$bad],
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(1)
        ->and($run['output'])->toContain('extension no soportada (solo .pdf y .xml).')
        ->toContain('No hay archivos PDF/XML que procesar.')
        ->not->toContain('Salida:');

    expect(is_dir($this->out))->toBeFalse();
});

it('reporta una carpeta pasada como archivo suelto', function (): void {
    $this->artisan('cfdi:autofile', [
        'files' => [$this->in],
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('es una carpeta (usa --dir).')
        ->assertExitCode(1);
});

it('reporta un --dir que no es carpeta', function (): void {
    $this->artisan('cfdi:autofile', [
        '--dir' => $this->tmp.'/nope',
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ])
        ->expectsOutputToContain('no es una carpeta.')
        ->assertExitCode(1);
});

it('imprime las advertencias una vez por archivo', function (): void {
    stage($this->in, 'cfdi_nota_credito.xml', 'par.xml');
    file_put_contents($this->in.'/par.pdf', 'x');

    $run = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'ZZZ010101ZZZ',
        '--no-interactive' => true,
    ]);

    expect($run['code'])->toBe(0)
        ->and($run['output'])->toContain('Advertencias:')
        ->toContain('  ⚠ par.xml: tu RFC ZZZ010101ZZZ no aparece como emisor ni receptor')
        ->toContain('  ⚠ par.pdf: tu RFC ZZZ010101ZZZ no aparece como emisor ni receptor')
        ->toContain('2/2 procesados correctamente.');

    expect($this->out.'/2026-03-01 - SERVICIOS CONTABLES NORTE - 1160.00 (nota de credito).xml')->toBeFile();
});

it('indexa lo procesado sin copiarlo al archivo de la app', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');

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

it('la tabla sigue mostrando la nota copiado', function (): void {
    stage($this->in, 'ejemplo_cfdi.xml', 'factura01.xml');

    $result = runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect($result['output'])->toContain('copiado');
});

it('no indexa los rechazados', function (): void {
    stage($this->in, 'no_es_cfdi.xml', 'basura.xml');

    runAutofile([
        '--dir' => $this->in,
        '--output' => $this->out,
        '--rfc' => 'BBBB010101BBB',
        '--no-interactive' => true,
    ]);

    expect(Document::query()->count())->toBe(0);
});
