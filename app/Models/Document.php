<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An archived CFDI.
 *
 * It has no `run_id` on purpose: the same invoice can arrive from several runs
 * or from the command, and it outlives all of them. The natural key is the tax
 * folio; when there is no folio (a CFDI read from the PDF by heuristics) the
 * fallback is the file's checksum.
 *
 * `disk` tells the two modes apart: `documents` when the app saved its own copy
 * (web), `null` when the row merely points at a file living on the user's disk
 * (command). In the second case `path` is absolute and the file can disappear
 * without the app noticing.
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
 * @property string|null $drive_file_id
 * @property string|null $drive_link
 * @property Carbon|null $drive_uploaded_at
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
        'drive_file_id',
        'drive_link',
        'drive_uploaded_at',
    ];

    /**
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * The app saved its own copy, instead of just pointing at the user's.
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

    /**
     * The file has a copy in the user's Google Drive.
     */
    public function isOnDrive(): bool
    {
        return $this->drive_link !== null;
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
            // Without the cast, SQLite returns `11600` and the amount comes out
            // with no decimals.
            'total' => 'decimal:2',
            'drive_uploaded_at' => 'datetime',
        ];
    }
}
