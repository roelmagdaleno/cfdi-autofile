<?php

namespace App\Support\Runs;

use RuntimeException;
use ZipArchive;

/**
 * Empaqueta SOLO la carpeta de salida.
 *
 * Los rechazados nunca se copian a `salida/`, asi que su exclusion del ZIP es
 * estructural: no hay ningun filtro que se pueda olvidar.
 */
final class RunZipper
{
    public const string FILENAME = 'cfdis-renombrados.zip';

    public function __construct(private readonly RunStorage $storage) {}

    public function zip(string $runId): string
    {
        $files = $this->storage->outputFiles($runId);

        if ($files === []) {
            throw new RuntimeException('La corrida no produjo archivos.');
        }

        $path = $this->storage->disk()->path("{$runId}/".self::FILENAME);

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP.');
        }

        foreach ($files as $file) {
            $archive->addFile($file, basename($file));
        }

        $archive->close();

        return $path;
    }
}
