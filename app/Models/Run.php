<?php

namespace App\Models;

use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A web run.
 *
 * It replaces the old `{uuid}/run.json`: it survives a browser refresh, lets the
 * download route know whether the run finished, and gives the tests a stable
 * surface to assert against. Unlike the JSON, the row is NOT deleted by
 * `cfdi:clean`: only `files_purged_at` is set, so the history of what was
 * processed is never lost.
 *
 * @property string $id
 * @property string $step
 * @property int $total_files
 * @property int $ok_files
 * @property int $ignored_files
 * @property list<string> $warnings
 * @property list<array{rfc: string, legalName: string|null, name: string}> $new_counterparties
 * @property Carbon|null $files_purged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RunFile> $files
 */
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    public const string STEP_UPLOAD = 'upload';

    public const string STEP_COUNTERPARTIES = 'counterparties';

    public const string STEP_RESULTS = 'results';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'step',
        'total_files',
        'ok_files',
        'ignored_files',
        'warnings',
        'new_counterparties',
        'files_purged_at',
    ];

    /**
     * @return HasMany<RunFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(RunFile::class)->orderBy('position');
    }

    public function isFinished(): bool
    {
        return $this->step === self::STEP_RESULTS;
    }

    /**
     * The run's files are still on disk, i.e. `cfdi:clean` has not been through
     * here yet.
     */
    public function hasFiles(): bool
    {
        return $this->files_purged_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warnings' => 'array',
            'new_counterparties' => 'array',
            'files_purged_at' => 'datetime',
        ];
    }
}
