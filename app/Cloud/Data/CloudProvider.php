<?php

namespace App\Cloud\Data;

use App\Cloud\Contracts\CloudDestinations;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Google\GoogleCloudFiles;
use App\Cloud\Google\GoogleCloudOAuth;
use App\Cloud\Google\GoogleDestinations;

/**
 * Where the documents are uploaded.
 *
 * This enum IS the catalog: adding Dropbox or S3 means one case here, its four
 * `match` arms, the two classes they name, and the display name in every
 * locale. None of the arms has a `default`, so PHP itself refuses to run with a
 * case somebody forgot to wire, and `TranslationsTest` refuses to pass without
 * the copy.
 */
enum CloudProvider: string
{
    case GoogleDrive = 'google_drive';

    /**
     * What an install that never opened the settings screen uses.
     */
    public static function default(): self
    {
        return self::GoogleDrive;
    }

    public function label(): string
    {
        return (string) __('ui.cloud.provider.'.$this->value);
    }

    /**
     * @return class-string<CloudFiles>
     */
    public function filesClass(): string
    {
        return match ($this) {
            self::GoogleDrive => GoogleCloudFiles::class,
        };
    }

    /**
     * @return class-string<CloudDestinations>
     */
    public function destinationsClass(): string
    {
        return match ($this) {
            self::GoogleDrive => GoogleDestinations::class,
        };
    }

    /**
     * Every provider so far connects through an OAuth handshake, so this says
     * so plainly instead of pretending otherwise with a nullable return nothing
     * can produce. The day one connects with pasted keys instead, this becomes
     * `?string` and the `match` will not compile until that case decides -- and
     * that is when the connect and callback routes get their guard.
     *
     * @return class-string<CloudOAuth>
     */
    public function oauthClass(): string
    {
        return match ($this) {
            self::GoogleDrive => GoogleCloudOAuth::class,
        };
    }
}
