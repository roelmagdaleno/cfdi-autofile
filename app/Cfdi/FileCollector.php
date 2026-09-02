<?php

namespace App\Cfdi;

use App\Cfdi\Data\CollectedFiles;
use App\Cfdi\Data\CollectionError;
use App\Cfdi\Support\PathExpander;

/**
 * Recolecta las rutas procesables y describe por que se descarto cada archivo.
 *
 * Lo comparten el comando y la web: los mensajes de rechazo por extension deben
 * ser identicos en las dos interfaces.
 */
final class FileCollector
{
    /** @var list<string> */
    public const array EXTENSIONS = ['.pdf', '.xml'];

    /**
     * @param  list<string>  $files
     */
    public function collect(array $files, ?string $directory = null): CollectedFiles
    {
        /** @var list<string> $paths */
        $paths = [];
        /** @var list<CollectionError> $errors */
        $errors = [];

        foreach ($files as $raw) {
            $path = PathExpander::expand($raw);

            if (! file_exists($path)) {
                $errors[] = new CollectionError($raw, 'no existe.');

                continue;
            }

            if (is_dir($path)) {
                $errors[] = new CollectionError($raw, 'es una carpeta (usa --dir).');

                continue;
            }

            if (! in_array(strtolower(PathExpander::suffix($path)), self::EXTENSIONS, true)) {
                $errors[] = new CollectionError(basename($path), 'extension no soportada (solo .pdf y .xml).');

                continue;
            }

            $paths[] = $path;
        }

        if ($directory !== null && $directory !== '') {
            $base = PathExpander::expand($directory);

            if (! is_dir($base)) {
                $errors[] = new CollectionError("--dir {$directory}", 'no es una carpeta.');
            } else {
                $entries = scandir($base) ?: [];
                sort($entries, SORT_STRING);

                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }

                    $child = rtrim($base, '/').'/'.$entry;

                    if (is_file($child) && in_array(strtolower(PathExpander::suffix($child)), self::EXTENSIONS, true)) {
                        $paths[] = $child;
                    }
                }
            }
        }

        // Deduplica por ruta resuelta, conservando la primera forma de escribirla.
        /** @var list<string> $unique */
        $unique = [];
        $seen = [];

        foreach ($paths as $path) {
            $resolved = realpath($path);
            $key = $resolved === false ? $path : $resolved;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $path;
            }
        }

        return new CollectedFiles($unique, $errors);
    }
}
