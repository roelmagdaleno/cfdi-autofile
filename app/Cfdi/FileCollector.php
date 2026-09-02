<?php

namespace App\Cfdi;

use App\Cfdi\Data\CollectedFiles;
use App\Cfdi\Data\CollectionError;
use App\Cfdi\Support\PathExpander;

/**
 * Collects the processable paths and describes why each file was discarded.
 *
 * The command and the web share it: the rejection messages for an unsupported
 * extension must be identical in both interfaces.
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
                $errors[] = new CollectionError($raw, (string) __('cfdi.collection.missing'));

                continue;
            }

            if (is_dir($path)) {
                $errors[] = new CollectionError($raw, (string) __('cfdi.collection.is_directory'));

                continue;
            }

            if (! in_array(strtolower(PathExpander::suffix($path)), self::EXTENSIONS, true)) {
                $errors[] = new CollectionError(basename($path), (string) __('cfdi.collection.unsupported_extension'));

                continue;
            }

            $paths[] = $path;
        }

        if ($directory !== null && $directory !== '') {
            $base = PathExpander::expand($directory);
            $isDirectory = is_dir($base);

            if (! $isDirectory) {
                $errors[] = new CollectionError("--dir {$directory}", (string) __('cfdi.collection.not_a_directory'));
            }

            if ($isDirectory) {
                $paths = [...$paths, ...$this->processableFilesIn($base)];
            }
        }

        // Deduplicates by resolved path, keeping the first spelling of it.
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

    /**
     * The direct children of $base with a supported extension, sorted by name
     * so the processing order does not depend on the filesystem.
     *
     * @return list<string>
     */
    private function processableFilesIn(string $base): array
    {
        $entries = scandir($base) ?: [];
        sort($entries, SORT_STRING);

        /** @var list<string> $files */
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = rtrim($base, '/').'/'.$entry;

            if (! is_file($child)) {
                continue;
            }

            if (! in_array(strtolower(PathExpander::suffix($child)), self::EXTENSIONS, true)) {
                continue;
            }

            $files[] = $child;
        }

        return $files;
    }
}
