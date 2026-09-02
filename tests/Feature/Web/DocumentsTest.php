<?php

use App\Livewire\BrowseDocuments;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Un documento archivado con su archivo de verdad en el disco fingido.
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

it('lista los documentos archivados', function (): void {
    $document = archivedDocument(['name' => '2026-02-14 G CONTADOR - 11600.00.xml']);

    Livewire::test(BrowseDocuments::class)
        ->assertSee('2026-02-14 G CONTADOR - 11600.00.xml')
        ->assertSee($document->counterparty->name)
        ->assertSee('11600.00');
});

it('filtra por nombre', function (): void {
    archivedDocument(['name' => '2026-02-14 G CONTADOR - 11600.00.xml']);
    archivedDocument(['name' => '2026-03-01 G CAFETERIA - 250.00.xml']);

    Livewire::test(BrowseDocuments::class)
        ->set('search', 'CAFETERIA')
        ->assertSee('CAFETERIA')
        ->assertDontSee('2026-02-14 G CONTADOR - 11600.00.xml');
});

it('filtra por folio fiscal', function (): void {
    $document = archivedDocument();
    archivedDocument();

    Livewire::test(BrowseDocuments::class)
        ->set('search', $document->folio)
        ->assertSee($document->name)
        ->assertCount('documents', 1);
});

it('filtra por contraparte', function (): void {
    $mine = archivedDocument(['name' => 'de-la-mia.xml']);
    archivedDocument(['name' => 'de-la-otra.xml']);

    Livewire::test(BrowseDocuments::class)
        ->set('counterpartyId', (string) $mine->counterparty_id)
        ->assertSee('de-la-mia.xml')
        ->assertDontSee('de-la-otra.xml');
});

it('filtra por tipo', function (): void {
    archivedDocument(['name' => 'gasto.xml', 'type_letter' => 'G']);
    archivedDocument(['name' => 'ingreso.xml', 'type_letter' => 'I']);

    Livewire::test(BrowseDocuments::class)
        ->set('typeLetter', 'I')
        ->assertSee('ingreso.xml')
        ->assertDontSee('gasto.xml');
});

it('descarga el archivo con un enlace firmado', function (): void {
    $document = archivedDocument();

    $response = $this->get(signedDocumentDownload($document));

    $response->assertOk()->assertDownload($document->name);
});

it('responde 403 sin firma', function (): void {
    $document = archivedDocument();

    $this->get("/documentos/{$document->id}/descargar")->assertForbidden();
});

it('responde 404 cuando el archivo ya no esta donde lo indexamos', function (): void {
    $document = Document::factory()->indexedInPlace('/tmp/no-existe/factura.xml')->create();

    $this->get(signedDocumentDownload($document))->assertNotFound();
});

it('avisa en la tabla cuando el archivo ya no existe', function (): void {
    Document::factory()->indexedInPlace('/tmp/no-existe/factura.xml')->create();

    Livewire::test(BrowseDocuments::class)->assertSee('Archivo no encontrado');
});

it('borra el documento y su copia archivada', function (): void {
    $document = archivedDocument();

    Livewire::test(BrowseDocuments::class)
        ->call('confirmDelete', $document->id)
        ->call('delete');

    Storage::disk(Document::DISK)->assertMissing($document->path);
    $this->assertModelMissing($document);
});

it('al borrar no toca el archivo del usuario', function (): void {
    $path = cfdiTempDir().'/factura.xml';
    copy(cfdiFixture('ejemplo_cfdi.xml'), $path);

    $document = Document::factory()->indexedInPlace($path)->create();

    Livewire::test(BrowseDocuments::class)
        ->call('confirmDelete', $document->id)
        ->call('delete');

    $this->assertModelMissing($document);
    expect($path)->toBeFile();

    unlink($path);
});

it('el enlace de una contraparte lleva a sus documentos', function (): void {
    $document = archivedDocument();

    $this->get(route('counterparties.index'))
        ->assertOk()
        ->assertSee(route('documents.index', ['contraparte' => $document->counterparty_id]), escape: false);
});
