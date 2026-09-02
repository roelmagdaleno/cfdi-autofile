<?php

use App\Livewire\BrowseDocuments;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * An archived document with a real file on the faked disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function archivedDocument(array $attributes = []): Document
{
    $document = Document::factory()->create($attributes);

    Storage::disk(Document::DISK)->put($document->path, '<xml/>');

    return $document;
}

function signedDocumentDownload(Document $document): string
{
    return URL::temporarySignedRoute('documents.download', now()->addHour(), ['document' => $document->id]);
}

it('lists the archived documents', function (): void {
    $document = archivedDocument(['name' => '2026-02-14 G CONTADOR - 11600.00.xml']);

    Livewire::test(BrowseDocuments::class)
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertSee($document->counterparty->name)
        ->assertSee('11600.00');
});

it('filters by name', function (): void {
    archivedDocument(['name' => '2026-02-14 G CONTADOR - 11600.00.xml']);
    archivedDocument(['name' => '2026-03-01 G CAFETERIA - 250.00.xml']);

    Livewire::test(BrowseDocuments::class)
        ->set('search', 'CAFETERIA')
        ->assertSee('CAFETERIA')
        ->assertDontSee('2026-02-14 G CONTADOR - 11600.00.xml');
});

it('filters by tax folio', function (): void {
    $document = archivedDocument();
    archivedDocument();

    Livewire::test(BrowseDocuments::class)
        ->set('search', $document->folio)
        ->assertSee($document->name)
        ->assertCount('documents', 1);
});

it('filters by counterparty', function (): void {
    $mine = archivedDocument(['name' => 'mine.xml']);
    archivedDocument(['name' => 'the-others.xml']);

    Livewire::test(BrowseDocuments::class)
        ->set('counterpartyId', (string) $mine->counterparty_id)
        ->assertSee('mine.xml')
        ->assertDontSee('the-others.xml');
});

it('filters by type', function (): void {
    archivedDocument(['name' => 'expense.xml', 'type_letter' => 'G']);
    archivedDocument(['name' => 'income.xml', 'type_letter' => 'I']);

    Livewire::test(BrowseDocuments::class)
        ->set('typeLetter', 'I')
        ->assertSee('income.xml')
        ->assertDontSee('expense.xml');
});

it('downloads the file with a signed link', function (): void {
    $document = archivedDocument();

    $response = $this->get(signedDocumentDownload($document));

    $response->assertOk()->assertDownload($document->name);
});

it('responds 403 without a signature', function (): void {
    $document = archivedDocument();

    $this->get("/documents/{$document->id}/download")->assertForbidden();
});

it('responds 404 when the file is no longer where it was indexed', function (): void {
    $document = Document::factory()->indexedInPlace('/tmp/does-not-exist/invoice.xml')->create();

    $this->get(signedDocumentDownload($document))->assertNotFound();
});

it('warns in the table when the file no longer exists', function (): void {
    Document::factory()->indexedInPlace('/tmp/does-not-exist/invoice.xml')->create();

    Livewire::test(BrowseDocuments::class)->assertSee('File not found');
});

it('deletes the document and its archived copy', function (): void {
    $document = archivedDocument();

    Livewire::test(BrowseDocuments::class)
        ->call('confirmDelete', $document->id)
        ->call('delete');

    Storage::disk(Document::DISK)->assertMissing($document->path);
    $this->assertModelMissing($document);
});

it('deleting leaves the user file untouched', function (): void {
    $path = cfdiTempDir().'/invoice.xml';
    copy(cfdiFixture('example_cfdi.xml'), $path);

    $document = Document::factory()->indexedInPlace($path)->create();

    Livewire::test(BrowseDocuments::class)
        ->call('confirmDelete', $document->id)
        ->call('delete');

    $this->assertModelMissing($document);
    expect($path)->toBeFile();

    unlink($path);
});

it('a counterparty link leads to its documents', function (): void {
    $document = archivedDocument();

    $this->get(route('counterparties.index'))
        ->assertOk()
        ->assertSee(route('documents.index', ['counterparty' => $document->counterparty_id]), escape: false);
});
