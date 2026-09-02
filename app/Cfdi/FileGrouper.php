<?php

namespace App\Cfdi;

use App\Cfdi\Data\FileGroup;
use App\Cfdi\Support\PathExpander;

/**
 * Agrupa por (carpeta resuelta, nombre base) para tratar PDF+XML como una sola
 * factura. Se preserva el orden de primera aparicion.
 */
final class FileGrouper
{
    /**
     * @param  list<string>  $paths
     * @return list<FileGroup>
     */
    public function group(array $paths): array
    {
        /** @var array<string, FileGroup> $groups */
        $groups = [];
        /** @var list<string> $order */
        $order = [];

        foreach ($paths as $path) {
            $directory = realpath(dirname($path));
            $stem = PathExpander::stem($path);
            $key = ($directory === false ? dirname($path) : $directory)."\0".$stem;

            if (! isset($groups[$key])) {
                $groups[$key] = new FileGroup($stem);
                $order[] = $key;
            }

            $slot = ltrim(strtolower(PathExpander::suffix($path)), '.');

            if ($slot === 'xml') {
                $groups[$key]->xml = $path;
            } elseif ($slot === 'pdf') {
                $groups[$key]->pdf = $path;
            }
        }

        return array_map(fn (string $key): FileGroup => $groups[$key], $order);
    }
}
