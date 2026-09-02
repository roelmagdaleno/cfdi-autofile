<?php

namespace App\Models;

use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una corrida de la web.
 *
 * Sustituye al viejo `{uuid}/run.json`: sobrevive a un refresh del navegador,
 * deja que la ruta de descarga sepa si la corrida termino, y le da a los tests
 * una superficie estable donde asertar. A diferencia del JSON, la fila NO se
 * borra con `cfdi:clean`: solo se marca `files_purged_at`, asi que el historial
 * de lo que se proceso nunca se pierde.
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
     * Los archivos de la corrida siguen en disco, es decir `cfdi:clean` no ha
     * pasado por aqui.
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
