<?php

use App\Cfdi\Naming\Placeholder;
use App\Cfdi\Naming\TypeLetters;
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
    // `Storage::fake('runs')` points the disk at a REAL directory under
    // storage/framework/testing, which Laravel cleans between tests. That is why
    // the orchestrator can genuinely write and the tests stay isolated.
    Storage::fake(RunStorage::DISK);
    Storage::fake('local'); // Livewire temporaries

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

it('processes straight through when the counterparty is already cached', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertSee('OK')
        ->assertSee('1/1 processed successfully.');

    expect(outputNames($component->get('runId')))->toBe(['2026-02-14 G CONTADOR - 11600.00.xml']);
});

it('asks for the new counterparties and writes nothing yet', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_COUNTERPARTIES)
        ->assertSet('newCounterparties.0.rfc', 'AAA010101AAA')
        ->assertSet('newCounterparties.0.name', 'SERVICIOS CONTABLES NORTE')
        ->assertSee('SERVICIOS CONTABLES DEL NORTE SA DE CV');

    // Nothing persisted, nothing written.
    expect(Counterparty::count())->toBe(0)
        ->and(outputNames($component->get('runId')))->toBe([]);

    // The original did land in input/.
    expect(array_map('basename', $this->storage->inputFiles($component->get('runId'))))
        ->toBe(['invoice01.xml']);
});

it('saves the edited name and produces the file under it', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->set('newCounterparties.0.name', 'Contador Norte')
        ->call('confirmCounterparties')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('1/1 processed successfully.');

    $this->assertDatabaseHas('counterparties', [
        'rfc' => 'AAA010101AAA',
        'name' => 'Contador Norte',
        'legal_name' => 'SERVICIOS CONTABLES DEL NORTE SA DE CV',
    ]);

    expect(outputNames($component->get('runId')))->toBe(['2026-02-14 G Contador Norte - 11600.00.xml']);
});

it('sanitizes the name the user typed', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'f.xml')])
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

it('requires a name for every new counterparty', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->set('newCounterparties.0.name', '')
        ->call('confirmCounterparties')
        ->assertHasErrors('newCounterparties.0.name')
        ->assertSet('step', RunCfdis::STEP_COUNTERPARTIES);

    expect(Counterparty::count())->toBe(0);
});

it('an unsupported extension is a rejected row, not a validation error', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('REJECTED')
        ->assertSee('unsupported extension')
        ->assertSee('0/0 processed successfully.')
        ->assertSee('1 file(s) ignored');

    expect(outputNames($component->get('runId')))->toBe([]);
});

it('rejects an XML that is not a CFDI and keeps the original', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('not_a_cfdi.xml', 'junk.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('REJECTED')
        ->assertSee('not a valid CFDI')
        ->assertSee('0/1 processed successfully.');

    $runId = $component->get('runId');

    expect(outputNames($runId))->toBe([])
        ->and(array_map('basename', $this->storage->inputFiles($runId)))->toBe(['junk.xml']);
});

it('does not process without a configured RFC', function (): void {
    app(AppSettings::class)->setRfc('');

    Livewire::test(RunCfdis::class)
        ->assertSee('Your RFC is not configured yet')
        ->set('files', [fixtureUpload('example_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->assertHasErrors('files')
        ->assertSet('runId', '')
        ->assertSet('step', RunCfdis::STEP_UPLOAD);

    expect(Storage::disk(RunStorage::DISK)->directories())->toBe([]);
});

it('rejects more files than the cap', function (): void {
    $files = [];

    for ($i = 0; $i <= RunCfdis::MAX_FILES; $i++) {
        $files[] = fixtureUpload('example_cfdi.xml', "f{$i}.xml");
    }

    Livewire::test(RunCfdis::class)
        ->set('files', $files)
        ->call('analyze')
        ->assertHasErrors(['files' => 'max']);
});

it('pairs the PDF and the XML sharing a base name', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('example_cfdi.xml', 'invoice.xml'),
            UploadedFile::fake()->createWithContent('invoice.pdf', '%PDF-1.4 fake'),
        ])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSee('2/2 processed successfully.');

    // Both end up with the same new name, taken from the XML.
    expect(outputNames($component->get('runId')))->toBe([
        '2026-02-14 G CONTADOR - 11600.00.pdf',
        '2026-02-14 G CONTADOR - 11600.00.xml',
    ]);
});

it('rehydrates the run from the URL', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->get('runId');

    Livewire::withQueryParams(['run' => $runId])
        ->test(RunCfdis::class)
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSet('runId', $runId)
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertSee('1/1 processed successfully.');
});

it('an unknown run falls back to the upload step', function (): void {
    Livewire::withQueryParams(['run' => (string) Str::uuid()])
        ->test(RunCfdis::class)
        ->assertSet('step', RunCfdis::STEP_UPLOAD)
        ->assertSet('runId', '');
});

it('startOver clears the run', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->call('startOver')
        ->assertSet('step', RunCfdis::STEP_UPLOAD)
        ->assertSet('runId', '')
        ->assertDontSee('2026-02-14 G CONTADOR - 11600.00.xml');
});

it('does not duplicate files when the same run is processed twice', function (): void {
    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'f.xml')])
        ->call('analyze')
        ->call('confirmCounterparties')
        ->call('confirmCounterparties');

    expect(outputNames($component->get('runId')))
        ->toBe(['2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml']);
});

it('accumulates what is dropped on top of the current selection', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->assertSet('incomingFiles', [])
        ->set('incomingFiles', [UploadedFile::fake()->createWithContent('invoice01.pdf', '%PDF-1.4 fake')])
        ->assertHasNoErrors()
        ->assertCount('files', 2)
        ->assertSee('invoice01.xml')
        ->assertSee('invoice01.pdf');
});

it('ignores a file already selected with the same name and size', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->set('incomingFiles', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->assertHasNoErrors()
        ->assertCount('files', 1);
});

it('stops at the cap when accumulating and reports how many it ignored', function (): void {
    $files = [];

    for ($i = 0; $i <= RunCfdis::MAX_FILES; $i++) {
        $files[] = fixtureUpload('example_cfdi.xml', "f{$i}.xml");
    }

    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', $files)
        ->assertHasErrors('files')
        ->assertSee('1 were ignored')
        ->assertCount('files', RunCfdis::MAX_FILES);
});

it('removes a file from the selection', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [
            fixtureUpload('example_cfdi.xml', 'invoice01.xml'),
            fixtureUpload('example_cfdi.xml', 'invoice02.xml'),
        ])
        ->call('removeFile', 0)
        ->assertCount('files', 1)
        ->assertDontSee('invoice01.xml')
        ->assertSee('invoice02.xml');
});

it('processes what was accumulated by dropping', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('incomingFiles', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSet('step', RunCfdis::STEP_RESULTS)
        ->assertSee('1/1 processed successfully.');
});

it('shows the RFC, the template and an example name', function (): void {
    app(AppSettings::class)->setTemplate('{date} {type} - {folio}');

    Livewire::test(RunCfdis::class)
        ->assertSee('BBBB010101BBB')
        // The description only shows up in the pill's tooltip: it confirms the
        // template was drawn piece by piece and not as plain text.
        ->assertSee(Placeholder::Date->label())
        ->assertSee(Placeholder::Date->description())
        ->assertSee(Placeholder::Type->description())
        ->assertSee('{folio}')
        ->assertSee('2026-02-14 G - {folio}.xml');
});

it('names the file with the configured label', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    app(AppSettings::class)->setTypeLetters(new TypeLetters(income: 'E', expense: 'R'));

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSee('2026-02-14 R CONTADOR - 11600.00.xml');

    expect(outputNames($component->get('runId')))->toBe(['2026-02-14 R CONTADOR - 11600.00.xml']);
});

it('describes the type piece with the configured labels', function (): void {
    app(AppSettings::class)->setTypeLetters(new TypeLetters(income: 'E', expense: 'R'));

    Livewire::test(RunCfdis::class)
        ->assertSee('E if you are the issuer, R if you are the recipient.')
        ->assertSee('2026-02-14 R SERVICIOS CONTABLES NORTE - 11600.00.xml');
});

it('stores the run result in the database', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('example_cfdi.xml', 'invoice01.xml'),
            UploadedFile::fake()->createWithContent('notes.txt', 'nothing'),
        ])
        ->call('analyze')
        ->get('runId');

    $run = Run::query()->findOrFail($runId);

    expect($run->step)->toBe(Run::STEP_RESULTS)
        ->and($run->ok_files)->toBe(1)
        ->and($run->ignored_files)->toBe(1);

    // The collection discards come first, exactly like in the command.
    expect($run->files->pluck('original_name')->all())->toBe(['notes.txt', 'invoice01.xml'])
        ->and($run->files->pluck('status')->all())->toBe(['rejected', 'ok']);
});

it('archives the CFDI and assigns it to its counterparty', function (): void {
    $counterparty = Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze');

    $document = Document::query()->sole();

    expect($document->counterparty_id)->toBe($counterparty->id)
        ->and($document->name)->toBe('2026-02-14 G CONTADOR - 11600.00.xml');

    Storage::disk(Document::DISK)
        ->assertExists('AAA010101AAA/2026/2026-02-14 G CONTADOR - 11600.00.xml');
});

it('the results table does not show the copied note', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertDontSee('copied');
});

it('the results table shows the rejection reason', function (): void {
    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('not_a_cfdi.xml', 'junk.xml')])
        ->call('analyze')
        ->assertSee('REJECTED')
        ->assertSee('This XML is not a valid CFDI');
});

it('offers a download for every archived file from the table', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertSee(route('documents.download', ['document' => Document::query()->sole()->id]), escape: false);
});

it('uploads the run to drive and records the link on the run and the document', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    $files = fakeCloud();

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->get('runId');

    $runFile = Run::query()->findOrFail($runId)->files->sole();
    $document = Document::query()->sole();

    // The fixture is dated 2026-02-14 and the configured RFC is the recipient,
    // so it is an expense: it belongs in that year's "received" folder.
    expect($files->folderOf($document->name))->toBe('2026 - Recibidas')
        ->and($runFile->drive_status)->toBe('uploaded')
        ->and($runFile->drive_link)->toBe($document->drive_link)
        ->and($runFile->drive_note)->toBeNull()
        ->and($document->drive_link)->not->toBeNull();
});

// Off, nothing leaves this machine and the columns stay empty.
it('leaves the drive columns alone while the integration is off', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->get('runId');

    $runFile = Run::query()->findOrFail($runId)->files->sole();

    expect($runFile->drive_status)->toBeNull()
        ->and($runFile->drive_link)->toBeNull()
        ->and(Document::query()->sole()->drive_link)->toBeNull();
});

// A discarded file was never placed, so it never had a shot at Drive. The row
// still has to be written: the insert is a single bulk statement.
it('leaves the drive columns empty on a discarded file', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    fakeCloud();

    $runId = Livewire::test(RunCfdis::class)
        ->set('files', [
            fixtureUpload('example_cfdi.xml', 'invoice01.xml'),
            UploadedFile::fake()->createWithContent('notes.txt', 'nothing'),
        ])
        ->call('analyze')
        ->get('runId');

    $rows = Run::query()->findOrFail($runId)->files;

    expect($rows->firstWhere('original_name', 'notes.txt')->drive_status)->toBeNull()
        ->and($rows->firstWhere('original_name', 'invoice01.xml')->drive_status)->toBe('uploaded');
});

it('records the reason and warns when an upload fails', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    $files = fakeCloud();
    $files->failUpload = '2026-02-14 G CONTADOR - 11600.00.xml';

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze');

    $runFile = Run::query()->findOrFail($component->get('runId'))->files->sole();

    expect($runFile->drive_status)->toBe('failed')
        ->and($runFile->drive_note)->toContain('storageQuotaExceeded')
        ->and($runFile->drive_link)->toBeNull()
        // The renaming still happened: a bad upload cannot undo the run.
        ->and($runFile->status)->toBe('ok')
        ->and($component->get('warnings'))->toHaveCount(1);
});

it('retries only the failed uploads', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    $files = fakeCloud();
    $files->failUpload = '2026-02-14 G CONTADOR - 11600.00.xml';

    $component = Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertSee(__('ui.cloud.retry'));

    $files->failUpload = null;

    $component->call('retryCloudUploads');

    $runFile = Run::query()->findOrFail($component->get('runId'))->files->sole();

    expect($runFile->drive_status)->toBe('uploaded')
        ->and($runFile->drive_link)->not->toBeNull()
        ->and(Document::query()->sole()->drive_link)->not->toBeNull();
});

it('offers no retry when nothing failed', function (): void {
    Counterparty::factory()->create(['rfc' => 'AAA010101AAA', 'name' => 'CONTADOR']);
    fakeCloud();

    Livewire::test(RunCfdis::class)
        ->set('files', [fixtureUpload('example_cfdi.xml', 'invoice01.xml')])
        ->call('analyze')
        ->assertSet('step', 'results')
        ->assertDontSee(__('ui.cloud.retry'))
        ->assertSee(__('ui.cloud.open', ['provider' => 'Google Drive']));
});
