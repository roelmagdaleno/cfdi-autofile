<?php

namespace App\Models;

use Database\Factories\CounterpartyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cache de RFC -> nombre corto de la contraparte.
 *
 * @property int $id
 * @property string $rfc
 * @property string $name
 * @property string|null $legal_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Document> $documents
 */
class Counterparty extends Model
{
    /** @use HasFactory<CounterpartyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['rfc', 'name', 'legal_name'];

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
