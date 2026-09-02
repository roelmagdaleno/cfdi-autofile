<?php

namespace App\Models;

use Database\Factories\RunFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de la tabla de resultados de una corrida, incluidos los rechazados.
 *
 * Es un snapshot inmutable de lo que paso ese dia. Por eso vive aparte de
 * `documents`: deduplicar un CFDI por folio no debe reescribir el resultado de
 * las corridas viejas.
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
