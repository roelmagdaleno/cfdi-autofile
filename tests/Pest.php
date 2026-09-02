<?php

use App\Cfdi\Parsing\PdfTextExtractor;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfTextExtractor;
use Tests\TestCase;

// El archivo permanente se finge SIEMPRE. Sin esto cualquier test que procese
// un CFDI escribiria en `storage/app/cfdis` de verdad, y como la base si se
// reinicia entre tests, el disco acumularia duplicados ` (2)`, ` (3)`, ...
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Storage::fake(Document::DISK))
    ->in('Feature');

/**
 * Ruta absoluta de un fixture de CFDI.
 */
function cfdiFixture(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/**
 * Directorio temporal aislado.
 *
 * El `realpath` es indispensable: en macOS `/var` es un symlink a `/private/var`
 * y sin resolverlo las comparaciones de rutas (agrupado, `Salida:`) no cuadran.
 */
function cfdiTempDir(): string
{
    $base = realpath(sys_get_temp_dir());
    $dir = $base.'/cfdi-'.bin2hex(random_bytes(6));

    mkdir($dir, 0777, true);

    return $dir;
}

/**
 * Upload falso con el contenido REAL del fixture.
 *
 * `UploadedFile::fake()->create()` genera archivos de cero bytes, que todos los
 * parsers rechazan; hay que usar `createWithContent`.
 */
function fixtureUpload(string $name, ?string $as = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $as ?? $name,
        (string) file_get_contents(cfdiFixture($name)),
    );
}

/**
 * Reemplaza el extractor de texto de PDF por uno que devuelve texto fijo.
 */
function fakePdfText(string $text): void
{
    app()->instance(PdfTextExtractor::class, new FakePdfTextExtractor($text));
}
