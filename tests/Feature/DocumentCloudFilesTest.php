<?php

use App\Cfdi\Data\TypeLetter;
use App\Cloud\CloudSync;
use App\Cloud\Data\CloudProvider;
use App\Models\Document;
use App\Models\DocumentCloudFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The table that survives a change of platform: one row per (document,
 * provider), so uploading somewhere else adds a row instead of overwriting the
 * one that says where the file already is.
 */
function cloudDocument(array $attributes = []): Document
{
    $document = Document::factory()->create([
        'issued_on' => '2026-02-14',
        'type_letter' => TypeLetter::Expense->value,
        ...$attributes,
    ]);

    Storage::disk(Document::DISK)->put($document->path, '<cfdi/>');

    return $document;
}

it('updates the row instead of adding one when the same document is synced again', function (): void {
    fakeCloud();

    $document = cloudDocument();

    app(CloudSync::class)->sync([$document]);
    app(CloudSync::class)->sync([$document->refresh()]);

    expect(DocumentCloudFile::query()->count())->toBe(1);
});

// The idempotence of a re-run is the database's promise, not the code's good
// intentions.
it('refuses a second row for the same document and provider', function (): void {
    $document = cloudDocument();

    DocumentCloudFile::factory()->for($document)->create(['provider' => CloudProvider::GoogleDrive]);

    expect(fn () => DocumentCloudFile::factory()->for($document)->create([
        'provider' => CloudProvider::GoogleDrive,
    ]))->toThrow(QueryException::class);
});

// The whole point of the table: switching platforms must not lose where the
// file already is. No second provider exists yet, so the other row is written
// straight to the table -- which is exactly the shape one would have -- and read
// back the same way, since the model would (rightly) refuse to hydrate a
// provider this build does not know.
it('leaves the copies of other providers alone', function (): void {
    fakeCloud();

    $document = cloudDocument();

    DB::table('document_cloud_files')->insert([
        'document_id' => $document->id,
        'provider' => 'somewhere_else',
        'remote_id' => 'their-id',
        'link' => 'https://example.com/their-id',
        'uploaded_at' => now(),
    ]);

    app(CloudSync::class)->sync([$document]);

    $rows = DB::table('document_cloud_files')->where('document_id', $document->id)->pluck('remote_id', 'provider');

    expect($rows)->toHaveCount(2)
        ->and($rows['somewhere_else'])->toBe('their-id')
        ->and($rows[CloudProvider::GoogleDrive->value])->toBe('file-1');
});

it('forgets where the file was when the document is deleted', function (): void {
    $document = cloudDocument();

    DocumentCloudFile::factory()->for($document)->create();

    $document->delete();

    expect(DocumentCloudFile::query()->count())->toBe(0);
});
