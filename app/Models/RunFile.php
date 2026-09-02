<?php

namespace App\Models;

use App\Cloud\Data\CloudProvider;
use App\Cloud\Data\CloudStatus;
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
 * @property string|null $cloud_provider
 * @property string|null $cloud_status
 * @property string|null $cloud_link
 * @property string|null $cloud_note
 * @property-read Run $run
 * @property-read Document|null $document
 */
class RunFile extends Model
{
    /** @use HasFactory<RunFileFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'run_id',
        'document_id',
        'position',
        'status',
        'original_name',
        'new_name',
        'note',
        'cloud_provider',
        'cloud_status',
        'cloud_link',
        'cloud_note',
    ];

    /**
     * What happened to this file on its way to the cloud, when the integration
     * was on for the run. `null` means the integration was off.
     */
    public function cloudStatus(): ?CloudStatus
    {
        return CloudStatus::tryFrom((string) $this->cloud_status);
    }

    /**
     * Which provider that was. Frozen onto the row on purpose: the run has to
     * keep saying who uploaded it that day even after the document moved
     * somewhere else.
     */
    public function cloudProvider(): ?CloudProvider
    {
        return CloudProvider::tryFrom((string) $this->cloud_provider);
    }

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
