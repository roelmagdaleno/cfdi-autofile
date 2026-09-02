<?php

namespace Database\Factories;

use App\Cloud\Data\CloudProvider;
use App\Models\Document;
use App\Models\DocumentCloudFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCloudFile>
 */
class DocumentCloudFileFactory extends Factory
{
    protected $model = DocumentCloudFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $remoteId = 'file-'.fake()->unique()->randomNumber(5);

        return [
            'document_id' => Document::factory(),
            'provider' => CloudProvider::GoogleDrive,
            'remote_id' => $remoteId,
            'link' => "https://drive.google.com/file/d/{$remoteId}/view",
            'uploaded_at' => now(),
        ];
    }
}
