<?php

use App\Cfdi\Naming\Placeholder;
use App\Livewire\RunCfdis;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Run;
use App\Support\AppSettings;
use App\Support\Runs\RunStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    // `Storage::fake('runs')` apunta el disco a un directorio REAL bajo
    // storage/framework/testing, que Laravel limpia entre tests. Por eso el
    // orquestador puede escribir de verdad y los tests siguen aislados.
    Storage::fake(RunStorage::DISK);
    Storage::fake('local'); // temporales de Livewire

    app(AppSettings::class)->setRfc('BBBB010101BBB');

    $this->storage = app(RunStorage::class);
});

/**
 * @return list<string>
 */
function outputNames(string $runId): array
{
    $names = array_map('basename', app(RunStorage::class)->outputFiles($runId));
    sort($names);

    return $names;
}

it('procesa directo cuando la contraparte ya esta cacheada', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertSee('OK')
        ->assertSee('1/1 procesados correctamente.');

    expect(outputNames($component->get('runId')))->toBe(['2026-02-14 G CONTADOR - 11600.00.xml']);
});

it('pide las contrapartes nuevas y todavia no escribe nada', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_COUNTERPARTIES)
        ->assertSet('newCounterparties.0.rfc', 'AAA010101AAA')
        ->assertSet('newCounterparties.0.name', 'SERVICIOS CONTABLES NORTE')
        ->assertSee('SERVICIOS CONTABLES DEL NORTE SA DE CV');

    // Nada persistido, nada escrito.
    expect(Counterparty::count())->toBe(0)
        ->and(outputNames($component->get('runId')))->toBe([]);

    // El original si quedo en entrada/.
    expect(array_map('basename', $this->storage->inputFiles($component->get('runId'))))
        ->toBe(['factura01.xml']);
});

it('guarda el nombre editado y produce el archivo con ese nombre', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze')
        ->set('newCounterparties.0.name', 'Contador Norte')
        ->call('confirmCounterparties')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('1/1 procesados correctamente.');

    $this->assertDatabaseHas('counterparties', [
        'rfc' => 'AAA010101AAA',
        'name' => 'Contador Norte',
        'legal_name' => 'SERVICIOS CONTABLES DEL NORTE SA DE CV',
    ]);

    expect(outputNames($component->get('runId')))->toBe(['2026-02-14 G Contador Norte - 11600.00.xml']);
});

it('sanitiza el nombre que escribio el usuario', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->set('newCounterparties.0.name', 'Café/Piñón')
        ->call('confirmCounterparties')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('counterparties', ['rfc' => 'AAA010101AAA', 'name' => 'Cafe-Pinon']);

    $names = outputNames($component->get('runId'));

    expect($names)->toBe(['2026-02-14 G Cafe-Pinon - 11600.00.xml'])
        ->and($names[0])->not->toContain('ñ')
        ->and($names[0])->not->toContain('$')
        ->and($names[0])->not->toContain(',');
});

it('exige un nombre para cada contraparte nueva', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->set('newCounterparties.0.name', '')
        ->call('confirmCounterparties')
        ->assertHasErrors('newCounterparties.0.name')
        ->assertSet('step', RunCfdis::STEP_COUNTERPARTIES);

    expect(Counterparty::count())->toBe(0);
});

it('una extension no soportada es una fila RECHAZADO, no un error de validacion', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [UploadedFile::fake()->createWithContent('notas.txt', 'hola')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('RECHAZADO')
        ->assertSee('extension no soportada')
        ->assertSee('0/0 procesados correctamente.')
        ->assertSee('1 archivo(s) ignorado(s)');

    expect(outputNames($component->get('runId')))->toBe([]);
});

it('rechaza un XML que no es CFDI y conserva el original', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('no_es_cfdi.xml', 'basura.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('RECHAZADO')
        ->assertSee('no es un CFDI valido')
        ->assertSee('0/1 procesados correctamente.');

    $runId = $component->get('runId');

    expect(outputNames($runId))->toBe([])
        ->and(array_map('basename', $this->storage->inputFiles($runId)))->toBe(['basura.xml']);
});

it('no procesa sin RFC configurado', function (): void {
    app(AppSettings::class)->setRfc('');

    Livewire::test(RunCfdis::class)
        ->assertSee('Falta configurar tu RFC')
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->assertHasErrors('files')
        ->assertSet('runId', '')
        ->assertSet('step', RunCfdis::STEP_UPLOAD);

    expect(Storage::disk(RunStorage::DISK)->directories())->toBe([]);
});

it('rechaza mas archivos que el tope', function (): void {
    $files = [];

    for ($i = 0; $i <= RunCfdis::MAX_FILES; $i++) {
        $files[] = fixtureUpload('ejemplo_cfdi.xml', "f{$i}.xml");
    }

    Livewire::test(RunCfdis::class)
        ->set('files', $files)
        ->call('analyze')
        ->assertHasErrors(['files' => 'max']);
});

it('empareja el PDF y el XML del mismo nombre base', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('ejemplo_cfdi.xml', 'factura.xml'),
            UploadedFile::fake()->createWithContent('factura.pdf', '%PDF-1.4 falso'),
        ])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSee('2/2 procesados correctamente.');

    // Los dos terminan con el mismo nombre nuevo, tomado del XML.
    expect(outputNames($component->get('runId')))->toBe([
        '2026-02-14 G CONTADOR - 11600.00.pdf',
        '2026-02-14 G CONTADOR - 11600.00.xml',
    ]);
});

it('rehidrata la corrida desde la URL', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->get('runId');

    Livewire::withQueryParams(['corrida' => $runId])
        ->test(RunCfdis::class)
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSet('runId', $runId)
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertSee('1/1 procesados correctamente.');
});

it('una corrida desconocida vuelve al paso de subida', function (): void {
    Livewire::withQueryParams(['corrida' => (string) Str::uuid()])
        ->test(RunCfdis::class)
        ->assertSet('step', RunCfdis::STEP_UPLOAD)
        ->assertSet('runId', '');
});

it('startOver limpia la corrida', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->call('startOver')
        ->assertSet('step', RunCfdis::STEP_UPLOAD)
        ->assertSet('runId', '')
        ->assertDontSee('2026-02-14 G CONTADOR - 11600.00.xml');
});

it('no duplica archivos si se procesa dos veces la misma corrida', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->call('confirmCounterparties')
        ->call('confirmCounterparties');

    expect(outputNames($component->get('runId')))
        ->toBe(['2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml']);
});

it('acumula lo que se suelta sobre lo ya seleccionado', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->assertSet('incomingFiles', [])
        ->set('incomingFiles', [UploadedFile::fake()->createWithContent('factura01.pdf', '%PDF-1.4 falso')])
        ->assertHasNoErrors()
        ->assertCount('files', 2)
        ->assertSee('factura01.xml')
        ->assertSee('factura01.pdf');
});

it('ignora un archivo ya seleccionado con el mismo nombre y tamanio', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->set('incomingFiles', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->assertHasNoErrors()
        ->assertCount('files', 1);
});

it('corta en el tope al acumular y avisa cuantos ignoro', function (): void {
    $files = [];

    for ($i = 0; $i <= RunCfdis::MAX_FILES; $i++) {
        $files[] = fixtureUpload('ejemplo_cfdi.xml', "f{$i}.xml");
    }

    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', $files)
        ->assertHasErrors('files')
        ->assertSee('se ignoraron 1')
        ->assertCount('files', RunCfdis::MAX_FILES);
});

it('quita un archivo de la seleccion', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [
            fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml'),
            fixtureUpload('ejemplo_cfdi.xml', 'factura02.xml'),
        ])
        ->call('removeFile', 0)
        ->assertCount('files', 1)
        ->assertDontSee('factura01.xml')
        ->assertSee('factura02.xml');
});

it('procesa lo que se acumulo por drop', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('1/1 procesados correctamente.');
});

it('muestra el RFC, la plantilla y un nombre de ejemplo', function (): void {
    app(AppSettings::class)->setTemplate('{fecha} {tipo} - {folio}');

    Livewire::test(RunCfdis::class)
        ->assertSee('BBBB010101BBB')
        // La descripcion solo sale en el tooltip de la pill: confirma que la
        // plantilla se dibujo pieza por pieza y no como texto plano.
        ->assertSee(Placeholder::Fecha->description())
        ->assertSee(Placeholder::Tipo->description())
        ->assertSee('{folio}')
        ->assertSee('2026-02-14 G - {folio}.xml');
});

it('guarda el resultado de la corrida en la base', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml'),
            UploadedFile::fake()->createWithContent('notas.txt', 'nada'),
        ])
        ->call('analyze')
        ->get('runId');

    $run = Run::query()->findOrFail($runId);

    expect($run->step)->toBe(Run::STEP_RESULTS)
        ->and($run->ok_files)->toBe(1)
        ->and($run->ignored_files)->toBe(1);

    // Los descartes de la recoleccion van primero, igual que en el comando.
    expect($run->files->pluck('original_name')->all())->toBe(['notas.txt', 'factura01.xml'])
        ->and($run->files->pluck('status')->all())->toBe(['RECHAZADO', 'OK']);
});

it('archiva el CFDI y lo asigna a su contraparte', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze');

    $document = Document::query()->sole();

    expect($document->counterparty_id)->toBe($counterparty->id)
        ->and($document->name)->toBe('2026-02-14 G CONTADOR - 11600.00.xml');

    Storage::disk(Document::DISK)
        ->assertExists('AAA010101AAA/2026/2026-02-14 G CONTADOR - 11600.00.xml');
});

it('la tabla de resultados no muestra la nota copiado', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze')
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertDontSee('copiado');
});

it('la tabla de resultados muestra el motivo del rechazo', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('no_es_cfdi.xml', 'basura.xml')])
        ->call('analyze')
        ->assertSee('RECHAZADO')
        ->assertSee('Este XML no es un CFDI valido');
});

it('ofrece descargar cada archivo archivado desde la tabla', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('ejemplo_cfdi.xml', 'factura01.xml')])
        ->call('analyze')
        ->assertSee(route('documents.download', ['document' => Document::query()->sole()->id]), escape: false);
});
