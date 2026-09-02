<?php

namespace App\Models;

use Database\Factories\RunFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a run's results table, rejected files included.
 *
 * It is an immutable snapshot of what happened that day. That is why it lives
 * apart from `documents`: deduplicating a CFDI by folio must not rewrite the
 * result of older runs.
 *
 * @property int $id
 * @property string $run_id
 * @property int|null $document_id
 * @property int $position
 * @property string $status
 * @property string $original_name
 * @property string|null $new_name
 * @property string|null $note
 * @property-read Run $run
 * @property-read Document|null $document
 */
class RunFile extends Model
{
    /** @use HasFactory<RunFileFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['run_id', 'document_id', 'position', 'status', 'original_name', 'new_name', 'note'];

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
