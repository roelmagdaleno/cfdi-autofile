<?php

namespace App\Cloud\Google;

use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Data\CloudFile;
use App\Cloud\Data\CloudUpload;
use App\Cloud\Exceptions\CloudRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Drive v3 over the `Http` facade.
 *
 * The official SDK (`google/apiclient`) is not used on purpose: even its latest
 * release pins `guzzlehttp/guzzle ^7.4.5` and `guzzlehttp/psr7 ^2.6`, while
 * Laravel 13 ships Guzzle 8 and psr7 3. Installing it would downgrade the HTTP
 * stack of the whole application. The six calls below are all this feature ever
 * needed from that SDK.
 *
 * Uploads are simple multipart: a CFDI is a few kilobytes of XML or PDF, and a
 * resumable session would be two extra round trips for nothing.
 */
final class GoogleCloudFiles implements CloudFiles
{
    private const string API = 'https://www.googleapis.com/drive/v3';

    private const string UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    public const string FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    public function __construct(private readonly GoogleAccessToken $token) {}

    public function ensureFolder(string $parentId, string $name): string
    {
        return $this->findFolder($parentId, $name) ?? $this->createFolder($parentId, $name);
    }

    public function upload(CloudUpload $upload): CloudFile
    {
        return $this->fileFrom(
            $this->multipart($upload, ['name' => $upload->name, 'parents' => [$upload->folderId]]),
            'upload',
        );
    }

    public function replace(string $fileId, CloudUpload $upload): CloudFile
    {
        return $this->fileFrom(
            $this->multipart($upload, ['name' => $upload->name], $fileId),
            'replace',
        );
    }

    public function accountEmail(): string
    {
        $response = $this->request()->get(self::API.'/about', ['fields' => 'user/emailAddress']);

        $this->checked($response, 'about');

        $email = $response->json('user.emailAddress');

        if (! is_string($email) || $email === '') {
            throw CloudRequestException::make('about', $response->status(), '');
        }

        return $email;
    }

    /**
     * The `q` filter is what makes this find a folder the user created by hand,
     * years before this application existed.
     */
    private function findFolder(string $parentId, string $name): ?string
    {
        $response = $this->request()->get(self::API.'/files', [
            'q' => sprintf(
                "name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
                $this->escape($name),
                self::FOLDER_MIME_TYPE,
                $this->escape($parentId),
            ),
            'fields' => 'files(id)',
            'pageSize' => 1,
            // Without these two a folder living in a shared drive is invisible.
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]);

        $this->checked($response, 'find_folder');

        $id = $response->json('files.0.id');

        return is_string($id) ? $id : null;
    }

    private function createFolder(string $parentId, string $name): string
    {
        $response = $this->request()->post(self::API.'/files?fields=id', [
            'name' => $name,
            'mimeType' => self::FOLDER_MIME_TYPE,
            'parents' => [$parentId],
        ]);

        $this->checked($response, 'create_folder');

        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            throw CloudRequestException::make('create_folder', $response->status(), '');
        }

        return $id;
    }

    /**
     * Drive's multipart upload wants the metadata and the bytes as two parts of
     * one `multipart/related` body. Laravel's `attach()` sends
     * `multipart/form-data`, which Drive rejects, so the body is assembled by
     * hand.
     *
     * A `$fileId` turns this into a replacement: same body, `PATCH`, and
     * `addParents`/`removeParents` so a file whose year or type changed moves
     * instead of ending up in both folders at once.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function multipart(CloudUpload $upload, array $metadata, ?string $fileId = null): Response
    {
        $boundary = 'cfdi'.bin2hex(random_bytes(12));

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$upload->mimeType}\r\n\r\n"
            .(string) file_get_contents($upload->localPath)."\r\n"
            ."--{$boundary}--";

        $request = $this->request()->withBody($body, "multipart/related; boundary={$boundary}");

        if ($fileId === null) {
            return $this->checked($request->post(self::UPLOAD_API.'/files?'.$this->uploadQuery()), 'upload');
        }

        $query = $this->uploadQuery([
            'addParents' => $upload->folderId,
            'removeParents' => $this->parentsOf($fileId),
        ]);

        return $this->checked($request->patch(self::UPLOAD_API."/files/{$fileId}?{$query}"), 'replace');
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function uploadQuery(array $extra = []): string
    {
        return http_build_query([
            'uploadType' => 'multipart',
            'fields' => 'id,webViewLink',
            'supportsAllDrives' => 'true',
            ...array_filter($extra, fn (string $value): bool => $value !== ''),
        ]);
    }

    /**
     * The folders a file currently sits in, so they can be removed when it
     * moves. An unreadable answer is not worth failing the upload over: the
     * worst case is a file listed in two folders.
     */
    private function parentsOf(string $fileId): string
    {
        $response = $this->request()->get(self::API."/files/{$fileId}", [
            'fields' => 'parents',
            'supportsAllDrives' => 'true',
        ]);

        if ($response->failed()) {
            return '';
        }

        $parents = $response->json('parents');

        return is_array($parents) ? implode(',', array_filter($parents, 'is_string')) : '';
    }

    private function fileFrom(Response $response, string $operation): CloudFile
    {
        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            throw CloudRequestException::make($operation, $response->status(), '');
        }

        $link = $response->json('webViewLink');

        return new CloudFile(
            id: $id,
            // Drive omits `webViewLink` for some file types; the canonical URL
            // works regardless, so the table always has something to link to.
            webViewLink: is_string($link) && $link !== '' ? $link : "https://drive.google.com/file/d/{$id}/view",
        );
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->token->value())
            ->acceptJson()
            ->timeout(60);
    }

    /**
     * Passes the response through, or turns Drive's error into ours.
     */
    private function checked(Response $response, string $operation): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw CloudRequestException::make(
            $operation,
            $response->status(),
            (string) $response->json('error.message', ''),
        );
    }

    /**
     * Drive's query language delimits string literals with single quotes and
     * escapes with a backslash. A counterparty called "O'Brien" would otherwise
     * produce an invalid query.
     */
    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
