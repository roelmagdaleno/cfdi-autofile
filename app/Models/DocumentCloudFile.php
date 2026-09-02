<?php

namespace App\Models;

use App\Cloud\Data\CloudProvider;
use Database\Factories\DocumentCloudFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The copy of a document that lives at one provider.
 *
 * There is at most one row per (document, provider) -- the database says so --
 * and `remote_id` is what a later run sends back to replace that same copy
 * instead of leaving a second one behind. A document that was uploaded to Drive
 * and then to somewhere else keeps both rows, so switching platforms never
 * loses the ids of the old one.
 *
 * @property int $id
 * @property int $document_id
 * @property CloudProvider $provider
 * @property string $remote_id
 * @property string $link
 * @property Carbon $uploaded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 */
class DocumentCloudFile extends Model
{
    /** @use HasFactory<DocumentCloudFileFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_id',
        'provider',
        'remote_id',
        'link',
        'uploaded_at',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CloudProvider::class,
            'uploaded_at' => 'datetime',
        ];
    }
}
