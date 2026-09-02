<?php

namespace Tests\Support;

use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Data\CloudFile;
use App\Cloud\Data\CloudUpload;
use App\Cloud\Exceptions\CloudRequestException;

/**
 * In-memory Google Drive.
 *
 * It is bound for the whole `Feature` suite in `tests/Pest.php`, so no test can
 * reach the network even by accident. It also records every call, which is how
 * the tests assert the two things that matter and are invisible from the
 * outside: that an existing folder is reused instead of duplicated, and that
 * reprocessing replaces a file instead of uploading a second copy.
 */
final class FakeCloudFiles implements CloudFiles
{
    /** @var array<string, string> folder name => id */
    public array $folders = [];

    /** @var list<string> names of the folders actually created */
    public array $created = [];

    /** @var list<array{parent: string, name: string}> */
    public array $lookups = [];

    /** @var array<string, CloudUpload> file id => last upload */
    public array $files = [];

    /** @var list<string> */
    public array $uploaded = [];

    /** @var list<string> */
    public array $replaced = [];

    public string $email = 'facturas@example.com';

    /** Folder name that blows up, to exercise the failure paths. */
    public ?string $failFolder = null;

    /** File name that blows up on upload. */
    public ?string $failUpload = null;

    /** Whether asking for the connected account blows up. */
    public bool $failAbout = false;

    private int $folderSequence = 0;

    private int $fileSequence = 0;

    /**
     * Pre-seeds a folder the user already had, so `ensureFolder` finds it
     * instead of creating one.
     */
    public function withFolder(string $name, string $id): self
    {
        $this->folders[$name] = $id;

        return $this;
    }

    public function ensureFolder(string $parentId, string $name): string
    {
        $this->lookups[] = ['parent' => $parentId, 'name' => $name];

        if ($this->failFolder === $name) {
            throw CloudRequestException::make('find_folder', 500, 'boom');
        }

        if (isset($this->folders[$name])) {
            return $this->folders[$name];
        }

        $this->created[] = $name;

        return $this->folders[$name] = 'folder-'.++$this->folderSequence;
    }

    public function upload(CloudUpload $upload): CloudFile
    {
        if ($this->failUpload === $upload->name) {
            throw CloudRequestException::make('upload', 403, 'storageQuotaExceeded');
        }

        $id = 'file-'.++$this->fileSequence;

        $this->files[$id] = $upload;
        $this->uploaded[] = $upload->name;

        return new CloudFile($id, "https://drive.google.com/file/d/{$id}/view");
    }

    public function replace(string $fileId, CloudUpload $upload): CloudFile
    {
        if (! isset($this->files[$fileId])) {
            throw CloudRequestException::make('replace', 404, "File not found: {$fileId}");
        }

        if ($this->failUpload === $upload->name) {
            throw CloudRequestException::make('replace', 403, 'storageQuotaExceeded');
        }

        $this->files[$fileId] = $upload;
        $this->replaced[] = $upload->name;

        return new CloudFile($fileId, "https://drive.google.com/file/d/{$fileId}/view");
    }

    public function accountEmail(): string
    {
        if ($this->failAbout) {
            throw CloudRequestException::make('about', 503, 'backendError');
        }

        return $this->email;
    }

    /**
     * The folder an uploaded file ended up in, by file name.
     */
    public function folderOf(string $name): ?string
    {
        foreach ($this->files as $upload) {
            if ($upload->name === $name) {
                return array_search($upload->folderId, $this->folders, true) ?: $upload->folderId;
            }
        }

        return null;
    }
}
