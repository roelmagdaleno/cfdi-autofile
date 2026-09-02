<?php

use App\Cfdi\Parsing\PdfTextExtractor;
use App\Cloud\Contracts\CloudDestinations;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Google\Contracts\GoogleFolders;
use App\Cloud\Google\GoogleDestinations;
use App\Cloud\Google\GoogleDriveConfig;
use App\Models\Document;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeCloudFiles;
use Tests\Support\FakePdfTextExtractor;
use Tests\TestCase;

// The permanent archive is ALWAYS faked. Without this, any test that processes a
// CFDI would write into the real `storage/app/cfdis`, and since the database is
// reset between tests but the disk is not, it would pile up ` (2)`, ` (3)`, …
// duplicates.
//
// Google Drive is ALWAYS faked, for the same reason: no test may reach the
// network. The integration is off by default, so nothing calls it unless a test
// turns it on -- but a bug that turned it on by accident would otherwise start
// talking to Google with the developer's own credentials.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Storage::fake(Document::DISK);

        bindFakeCloud(new FakeCloudFiles);

        // Belt and braces: any HTTP request that is not explicitly faked throws
        // instead of leaving the machine.
        Http::preventStrayRequests();
    })
    ->in('Feature');

// Unit tests boot the app too, without the database: the domain layer resolves
// its messages through `__()`, which needs the container and the translator.
pest()->extend(TestCase::class)->in('Unit');

/**
 * Absolute path of a CFDI fixture.
 */
function cfdiFixture(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/**
 * Isolated temporary directory.
 *
 * The `realpath` is indispensable: on macOS `/var` is a symlink to
 * `/private/var`, and without resolving it the path comparisons (grouping, the
 * `Output:` line) do not line up.
 */
function cfdiTempDir(): string
{
    $base = realpath(sys_get_temp_dir());
    $dir = $base.'/cfdi-'.bin2hex(random_bytes(6));

    mkdir($dir, 0777, true);

    return $dir;
}

/**
 * Fake upload carrying the REAL contents of the fixture.
 *
 * `UploadedFile::fake()->create()` produces zero-byte files, which every parser
 * rejects; `createWithContent` has to be used instead.
 */
function fixtureUpload(string $name, ?string $as = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $as ?? $name,
        (string) file_get_contents(cfdiFixture($name)),
    );
}

/**
 * Swaps the PDF text extractor for one that returns a fixed string.
 */
function fakePdfText(string $text): void
{
    app()->instance(PdfTextExtractor::class, new FakePdfTextExtractor($text));
}

/**
 * Points every cloud seam at the same in-memory provider.
 *
 * `CloudDestinations` is bound too, and not left to the container: it would
 * otherwise resolve a real `GoogleCloudFiles` to look folders up, which is a
 * second way out to the network.
 */
function bindFakeCloud(FakeCloudFiles $files): void
{
    app()->instance(CloudFiles::class, $files);
    app()->instance(GoogleFolders::class, $files);
    app()->bind(CloudDestinations::class, fn (): GoogleDestinations => new GoogleDestinations(
        $files,
        app(AppSettings::class)->drive(),
    ));
}

/**
 * Turns the Drive integration on with a connected account, and returns the fake
 * the container will hand to `CloudSync`.
 */
function fakeDrive(?FakeCloudFiles $files = null): FakeCloudFiles
{
    $files ??= new FakeCloudFiles;

    bindFakeCloud($files);

    $settings = app(AppSettings::class);
    $settings->setDrive(new GoogleDriveConfig(
        enabled: true,
        rootFolderId: GoogleDriveConfig::ROOT,
        incomeFolder: GoogleDriveConfig::DEFAULT_INCOME_FOLDER,
        expenseFolder: GoogleDriveConfig::DEFAULT_EXPENSE_FOLDER,
    ));
    $settings->setDriveRefreshToken('refresh-token-abc');
    $settings->setDriveAccountEmail('facturas@example.com');

    return $files;
}
