<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Un CFDI archivado.
 *
 * No tiene `run_id` a proposito: el mismo comprobante puede llegar desde varias
 * corridas o desde el comando, y sobrevive a todas. La llave natural es el folio
 * fiscal; cuando no hay folio (CFDI leido del PDF por heuristica) el fallback es
 * el checksum del archivo.
 *
 * `disk` distingue los dos modos: `documents` cuando la app guardo su propia
 * copia (web), `null` cuando la fila solo apunta a un archivo que vive en el
 * disco del usuario (comando). En el segundo caso `path` es absoluto y el
 * archivo puede desaparecer sin que la app se entere.
 *
 * @property int $id
 * @property int|null $counterparty_id
 * @property string|null $folio
 * @property string $extension
 * @property string $name
 * @property string $original_name
 * @property Carbon|null $issued_on
 * @property string|null $total
 * @property string|null $type_letter
 * @property string $confidence
 * @property string|null $source
 * @property string|null $disk
 * @property string $path
 * @property int|null $size
 * @property string|null $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Counterparty|null $counterparty
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const string DISK = 'documents';

    /** @var list<string> */
    protected $fillable = [
        'counterparty_id',
        'folio',
        'extension',
        'name',
        'original_name',
        'issued_on',
        'total',
        'type_letter',
        'confidence',
        'source',
        'disk',
        'path',
        'size',
        'checksum',
    ];

    /**
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * La app guardo su propia copia, en vez de solo apuntar a la del usuario.
     */
    public function isArchived(): bool
    {
        return $this->disk !== null;
    }

    public function absolutePath(): string
    {
        return $this->disk === null
            ? $this->path
            : Storage::disk($this->disk)->path($this->path);
    }

    public function fileExists(): bool
    {
        return is_file($this->absolutePath());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            // Sin el cast, SQLite devuelve `11600` y el monto sale sin decimales.
            'total' => 'decimal:2',
        ];
    }
}
