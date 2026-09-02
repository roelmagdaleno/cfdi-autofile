<?php

use App\Cfdi\Parsing\PdfTextExtractor;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfTextExtractor;
use Tests\TestCase;

// The permanent archive is ALWAYS faked. Without this, any test that processes a
// CFDI would write into the real `storage/app/cfdis`, and since the database is
// reset between tests but the disk is not, it would pile up ` (2)`, ` (3)`, …
// duplicates.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Storage::fake(Document::DISK))
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
