<?php

use App\Cfdi\Data\TypeLetter;
use App\Drive\Data\DriveConfig;
use App\Drive\Data\DriveStatus;
use App\Drive\DriveSync;
use App\Models\Document;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDriveFiles;

/**
 * A document whose file actually exists on the faked archive disk, which is
 * what `DriveSync` reads to build the upload.
 */
function driveDocument(array $attributes = []): Document
{
    $document = Document::factory()->create($attributes);

    Storage::disk(Document::DISK)->put($document->path, '<cfdi/>');

    return $document;
}

it('files an expense under the received folder for its year', function (): void {
    $files = fakeDrive();

    $document = driveDocument([
        'issued_on' => '2026-02-14',
        'type_letter' => TypeLetter::Expense->value,
        'name' => 'factura.xml',
    ]);

    $result = app(DriveSync::class)->sync([$document]);

    expect($result->outcomes[0]->status)->toBe(DriveStatus::Uploaded)
        ->and($files->uploaded)->toBe(['factura.xml'])
        ->and($files->folderOf('factura.xml'))->toBe('2026 - Recibidas');
});

it('files income under the issued folder for its year', function (): void {
    $files = fakeDrive();

    $document = driveDocument([
        'issued_on' => '2024-11-30',
        'type_letter' => TypeLetter::Income->value,
        'name' => 'cobro.xml',
    ]);

    app(DriveSync::class)->sync([$document]);

    expect($files->folderOf('cobro.xml'))->toBe('2024 - Emitidas');
});

// The whole point of the integration: the user's folders already exist, and a
// second folder with the same name would be worse than not uploading at all.
it('reuses a folder the user already had instead of creating one', function (): void {
    $files = fakeDrive((new FakeDriveFiles)->withFolder('2026 - Recibidas', 'existing-folder'));

    $document = driveDocument(['issued_on' => '2026-02-14', 'type_letter' => TypeLetter::Expense->value]);

    app(DriveSync::class)->sync([$document]);

    expect($files->created)->toBe([])
        ->and($files->files['file-1']->folderId)->toBe('existing-folder');
});

it('creates the folder of a year that does not exist yet', function (): void {
    $files = fakeDrive();

    $document = driveDocument(['issued_on' => '2027-01-03', 'type_letter' => TypeLetter::Income->value]);

    app(DriveSync::class)->sync([$document]);

    expect($files->created)->toBe(['2027 - Emitidas']);
});

// A run of twenty invoices must not ask Drive for the same folder twenty times.
it('resolves each folder once per sync', function (): void {
    $files = fakeDrive();

    $documents = [
        driveDocument(['issued_on' => '2026-01-05', 'type_letter' => TypeLetter::Expense->value, 'name' => 'a.xml']),
        driveDocument(['issued_on' => '2026-07-20', 'type_letter' => TypeLetter::Expense->value, 'name' => 'b.xml']),
        driveDocument(['issued_on' => '2026-09-01', 'type_letter' => TypeLetter::Income->value, 'name' => 'c.xml']),
    ];

    app(DriveSync::class)->sync($documents);

    expect($files->lookups)->toHaveCount(2)
        ->and($files->uploaded)->toHaveCount(3);
});

it('skips a document with no folder it could belong to', function (array $attributes, string $needle): void {
    $files = fakeDrive();

    $result = app(DriveSync::class)->sync([driveDocument($attributes)]);

    expect($result->outcomes[0]->status)->toBe(DriveStatus::Skipped)
        ->and($result->outcomes[0]->reason)->toContain($needle)
        ->and($result->outcomes[0]->link)->toBeNull()
        ->and($files->uploaded)->toBe([]);
})->with([
    'no issue date' => [['issued_on' => null], 'no issue date'],
    'RFC is not a party' => [['type_letter' => TypeLetter::Unknown->value], 'not on the CFDI'],
]);

// Only the command produces these: the row points at a file on the user's own
// disk, which they can move or delete without the app noticing.
it('skips a document whose file is gone', function (): void {
    $files = fakeDrive();

    $document = Document::factory()->indexedInPlace('/tmp/does-not-exist-'.uniqid().'.xml')->create();

    $result = app(DriveSync::class)->sync([$document]);

    expect($result->outcomes[0]->status)->toBe(DriveStatus::Skipped)
        ->and($files->uploaded)->toBe([]);
});

it('records where the file landed on the document', function (): void {
    fakeDrive();

    $document = driveDocument(['issued_on' => '2026-02-14', 'type_letter' => TypeLetter::Expense->value]);

    app(DriveSync::class)->sync([$document]);

    $document->refresh();

    expect($document->drive_file_id)->toBe('file-1')
        ->and($document->drive_link)->toBe('https://drive.google.com/file/d/file-1/view')
        ->and($document->drive_uploaded_at)->not->toBeNull()
        ->and($document->isOnDrive())->toBeTrue();
});

// Reprocessing the same CFDI is routine (a new template, a renamed
// counterparty). It has to update the file in Drive, not leave two copies.
it('replaces the file in place when the document was already uploaded', function (): void {
    $files = fakeDrive();

    $document = driveDocument(['issued_on' => '2026-02-14', 'type_letter' => TypeLetter::Expense->value, 'name' => 'v1.xml']);

    app(DriveSync::class)->sync([$document]);

    $document->refresh()->update(['name' => 'v2.xml']);

    app(DriveSync::class)->sync([$document->refresh()]);

    expect($files->uploaded)->toBe(['v1.xml'])
        ->and($files->replaced)->toBe(['v2.xml'])
        ->and($files->files)->toHaveCount(1)
        ->and($document->refresh()->drive_file_id)->toBe('file-1');
});

it('uploads again when the stored file no longer exists in drive', function (): void {
    $files = fakeDrive();

    $document = driveDocument([
        'issued_on' => '2026-02-14',
        'type_letter' => TypeLetter::Expense->value,
        'drive_file_id' => 'deleted-by-hand',
    ]);

    $result = app(DriveSync::class)->sync([$document]);

    expect($result->outcomes[0]->status)->toBe(DriveStatus::Uploaded)
        ->and($files->uploaded)->toHaveCount(1)
        ->and($document->refresh()->drive_file_id)->toBe('file-1');
});

// The files are already renamed and archived by this point; losing that over
// one bad upload would be absurd.
it('keeps uploading the rest when one file fails', function (): void {
    $files = fakeDrive();
    $files->failUpload = 'broken.xml';

    $documents = [
        driveDocument(['issued_on' => '2026-01-05', 'type_letter' => TypeLetter::Expense->value, 'name' => 'broken.xml']),
        driveDocument(['issued_on' => '2026-01-06', 'type_letter' => TypeLetter::Expense->value, 'name' => 'fine.xml']),
    ];

    $result = app(DriveSync::class)->sync($documents);

    expect($result->outcomes[0]->status)->toBe(DriveStatus::Failed)
        ->and($result->outcomes[0]->reason)->toContain('storageQuotaExceeded')
        ->and($result->outcomes[1]->status)->toBe(DriveStatus::Uploaded)
        ->and($files->uploaded)->toBe(['fine.xml'])
        ->and($result->warnings)->toHaveCount(1);
});

it('does nothing at all while the integration is off', function (): void {
    $files = fakeDrive();

    app(AppSettings::class)->setDrive(new DriveConfig(
        enabled: false,
        rootFolderId: DriveConfig::ROOT,
        incomeFolder: DriveConfig::DEFAULT_INCOME_FOLDER,
        expenseFolder: DriveConfig::DEFAULT_EXPENSE_FOLDER,
    ));

    $result = app(DriveSync::class)->sync([driveDocument()]);

    expect($result->outcomes)->toBe([])
        ->and($result->warnings)->toBe([])
        ->and($files->lookups)->toBe([])
        ->and($files->uploaded)->toBe([]);
});

// One reason, once, instead of the same row twenty times over.
it('fails everything with a single reason when no account is connected', function (): void {
    $files = fakeDrive();

    app(AppSettings::class)->disconnectDrive();

    $result = app(DriveSync::class)->sync([driveDocument(), driveDocument()]);

    expect($result->outcomes)->toHaveCount(2)
        ->and($result->outcomes[0]->status)->toBe(DriveStatus::Failed)
        ->and($result->outcomes[1]->reason)->toContain('No Google account is connected')
        ->and($result->warnings)->toHaveCount(1)
        ->and($files->lookups)->toBe([]);
});

it('preserves the keys it was given so a run can match its rows', function (): void {
    fakeDrive();

    $documents = [
        7 => driveDocument(['issued_on' => '2026-01-05', 'type_letter' => TypeLetter::Expense->value]),
        9 => driveDocument(['issued_on' => '2026-01-06', 'type_letter' => TypeLetter::Expense->value]),
    ];

    $result = app(DriveSync::class)->sync($documents);

    expect(array_keys($result->outcomes))->toBe([7, 9]);
});
