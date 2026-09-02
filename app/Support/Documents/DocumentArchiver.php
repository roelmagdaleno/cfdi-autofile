<?php

namespace App\Support\Documents;

use App\Cfdi\Data\FileResult;
use App\Cfdi\Data\PlacedFile;
use App\Cfdi\Data\ProcessResult;
use App\Cfdi\Support\PathExpander;
use App\Models\Counterparty;
use App\Models\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Indexa en `documents` lo que una corrida acaba de copiar o mover.
 *
 * Dos modos, y la diferencia es a proposito:
 *
 * - **Web** (`copyIntoArchive: true`). Los originales son subidas temporales que
 *   `cfdi:clean` borra, asi que la copia del archivo es la unica que va a
 *   quedar. Se guarda en el disco `documents`.
 * - **Comando** (`copyIntoArchive: false`). El archivo ya vive donde el usuario
 *   pidio con `--output`; copiarlo otra vez duplicaria cada byte sin ganar nada.
 *   Solo se indexa la ruta absoluta, y `disk` queda en `null`.
 */
final class DocumentArchiver
{
    private const string NO_RFC = 'SIN-RFC';

    private const string NO_DATE = 'sin-fecha';

    /**
     * Indexa las filas colocadas del resultado.
     *
     * @return array<int, Document> por indice de fila en `$result->rows`
     */
    public function archive(ProcessResult $result, bool $copyIntoArchive): array
    {
        $documents = [];

        foreach ($result->rows as $index => $row) {
            $document = $this->archiveRow($row, $copyIntoArchive);

            if ($document !== null) {
                $documents[$index] = $document;
            }
        }

        return $documents;
    }

    private function archiveRow(FileResult $row, bool $copyIntoArchive): ?Document
    {
        $placed = $row->placed;

        // Los rechazados nunca se colocaron: no hay archivo que indexar.
        if ($placed === null || ! is_file($placed->path)) {
            return null;
        }

        $checksum = hash_file('sha256', $placed->path) ?: null;
        $existing = $this->findExisting($placed, $checksum);

        $attributes = [
            'counterparty_id' => $this->counterpartyIdFor($placed->counterpartyRfc),
            'folio' => $placed->folio,
            'extension' => $placed->extension,
            'name' => basename($placed->path),
            'original_name' => $row->original,
            'issued_on' => $placed->issuedOn,
            'total' => $placed->total,
            'type_letter' => $placed->type->value,
            'confidence' => $placed->confidence->value,
            'source' => $placed->source?->value,
            'size' => filesize($placed->path) ?: null,
            'checksum' => $checksum,
            ...$copyIntoArchive
                ? ['disk' => Document::DISK, 'path' => $this->store($placed, $existing)]
                : ['disk' => null, 'path' => $placed->path],
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        return Document::query()->create($attributes);
    }

    /**
     * El folio fiscal es la llave natural. Cuando no hay (CFDI leido del PDF por
     * heuristica) el fallback es el contenido del archivo.
     */
    private function findExisting(PlacedFile $placed, ?string $checksum): ?Document
    {
        $query = Document::query()->where('extension', $placed->extension);

        if ($placed->folio !== null && $placed->folio !== '') {
            return $query->where('folio', $placed->folio)->first();
        }

        if ($checksum === null) {
            return null;
        }

        return $query->whereNull('folio')->where('checksum', $checksum)->first();
    }

    private function counterpartyIdFor(?string $rfc): ?int
    {
        if ($rfc === null || $rfc === '') {
            return null;
        }

        // Los RFC genericos resuelven a "General" sin crear fila, asi que aqui no
        // hay nada que encontrar y el documento se queda sin contraparte.
        return Counterparty::query()->where('rfc', $rfc)->value('id');
    }

    /**
     * Copia el archivo al disco permanente y devuelve su ruta relativa.
     */
    private function store(PlacedFile $placed, ?Document $existing): string
    {
        $disk = $this->disk();
        $target = $this->freePath($disk, $this->directoryFor($placed).'/'.basename($placed->path), $existing);

        $stream = fopen($placed->path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("No se pudo leer {$placed->path}.");
        }

        $disk->writeStream($target, $stream);
        fclose($stream);

        // Si el nombre cambio (otra plantilla, otro nombre de contraparte) el
        // archivo viejo se queda huerfano: hay que borrarlo aqui o nunca.
        if ($existing !== null && $existing->isArchived() && $existing->path !== $target) {
            $disk->delete($existing->path);
        }

        return $target;
    }

    private function directoryFor(PlacedFile $placed): string
    {
        $rfc = $placed->counterpartyRfc !== null && $placed->counterpartyRfc !== ''
            ? $placed->counterpartyRfc
            : self::NO_RFC;

        $year = $placed->issuedOn !== null && $placed->issuedOn !== ''
            ? substr($placed->issuedOn, 0, 4)
            : self::NO_DATE;

        return "{$rfc}/{$year}";
    }

    /**
     * `nombre (2).ext` cuando el lugar ya lo ocupa OTRO documento.
     *
     * Sobrescribir la ruta del propio documento es justo lo que queremos al
     * reprocesar el mismo folio; pisar la de otro seria perder un archivo.
     */
    private function freePath(Filesystem $disk, string $target, ?Document $existing): string
    {
        if ($existing !== null && $existing->isArchived() && $existing->path === $target) {
            return $target;
        }

        if (! $disk->fileExists($target)) {
            return $target;
        }

        $stem = PathExpander::stem($target);
        $suffix = PathExpander::suffix($target);
        $directory = dirname($target);

        for ($counter = 2; ; $counter++) {
            $candidate = "{$directory}/{$stem} ({$counter}){$suffix}";

            if (! $disk->fileExists($candidate)) {
                return $candidate;
            }
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(Document::DISK);
    }
}
