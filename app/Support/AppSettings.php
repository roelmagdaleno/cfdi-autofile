<?php

namespace App\Support;

use App\Cfdi\Naming\TypeLetters;
use App\Cloud\Data\CloudConfig;
use App\Cloud\Data\CloudProvider;
use App\Cloud\Google\GoogleDriveConfig;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed facade over the `settings` table.
 *
 * Values are deliberately not cached in memory: the Artisan command writes and
 * then reads back within the same run.
 */
final class AppSettings
{
    public const string DEFAULT_TEMPLATE = '{date} {type} {counterparty} - {amount}{credit_note}';

    public const string KEY_RFC = 'rfc';

    public const string KEY_TEMPLATE = 'template';

    public const string KEY_LETTER_INCOME = 'letter_income';

    public const string KEY_LETTER_EXPENSE = 'letter_expense';

    public const string KEY_CLOUD_ENABLED = 'cloud_enabled';

    public const string KEY_CLOUD_PROVIDER = 'cloud_provider';

    /**
     * Suffixes of the per-provider keys.
     *
     * Everything a provider owns is stored under `cloud_<provider>_<suffix>`, so
     * two providers can be configured -- and connected -- at the same time and
     * switching between them forgets nothing.
     */
    public const string SUFFIX_ROOT_FOLDER = 'root_folder_id';

    public const string SUFFIX_FOLDER_INCOME = 'folder_income';

    public const string SUFFIX_FOLDER_EXPENSE = 'folder_expense';

    public const string SUFFIX_CREDENTIAL = 'credential';

    public const string SUFFIX_ACCOUNT = 'account';

    public function rfc(): ?string
    {
        return $this->get(self::KEY_RFC);
    }

    public function setRfc(string $rfc): void
    {
        $this->put(self::KEY_RFC, mb_strtoupper(trim($rfc)));
    }

    public function template(): string
    {
        $template = $this->get(self::KEY_TEMPLATE);

        return $template === null || $template === '' ? self::DEFAULT_TEMPLATE : $template;
    }

    /**
     * The template is stored verbatim: it is neither validated nor trimmed.
     */
    public function setTemplate(string $template): void
    {
        $this->put(self::KEY_TEMPLATE, $template);
    }

    /**
     * The labels written into file names for income and expenses.
     *
     * A missing or blank row falls back to the default letter, so an install
     * that never touched this keeps producing `I` and `G`.
     */
    public function typeLetters(): TypeLetters
    {
        $defaults = TypeLetters::defaults();

        return new TypeLetters(
            income: $this->nonEmpty(self::KEY_LETTER_INCOME) ?? $defaults->income,
            expense: $this->nonEmpty(self::KEY_LETTER_EXPENSE) ?? $defaults->expense,
        );
    }

    public function setTypeLetters(TypeLetters $letters): void
    {
        $this->put(self::KEY_LETTER_INCOME, trim($letters->income));
        $this->put(self::KEY_LETTER_EXPENSE, trim($letters->expense));
    }

    /**
     * The provider-independent half of the integration.
     *
     * Both fields fall back to their default, so an install that never opened
     * the cloud card behaves exactly like one that predates the feature: the
     * integration is off and nothing reaches the network. An unreadable
     * provider falls back too rather than throwing -- a bad row must not take
     * the settings screen down with it.
     */
    public function cloud(): CloudConfig
    {
        return new CloudConfig(
            enabled: $this->get(self::KEY_CLOUD_ENABLED) === '1',
            provider: $this->cloudProvider(),
        );
    }

    public function setCloud(CloudConfig $config): void
    {
        $this->put(self::KEY_CLOUD_ENABLED, $config->enabled ? '1' : '0');
        $this->put(self::KEY_CLOUD_PROVIDER, $config->provider->value);
    }

    public function cloudProvider(): CloudProvider
    {
        return CloudProvider::tryFrom((string) $this->get(self::KEY_CLOUD_PROVIDER)) ?? CloudProvider::default();
    }

    /**
     * Where in Drive the files go. Every field falls back to its default, so an
     * install that never touched the folder names keeps using
     * `{year} - Emitidas` and `{year} - Recibidas`.
     */
    public function googleDrive(): GoogleDriveConfig
    {
        $defaults = GoogleDriveConfig::defaults();
        $provider = CloudProvider::GoogleDrive;

        return new GoogleDriveConfig(
            rootFolderId: $this->nonEmpty($this->providerKey($provider, self::SUFFIX_ROOT_FOLDER)) ?? $defaults->rootFolderId,
            incomeFolder: $this->nonEmpty($this->providerKey($provider, self::SUFFIX_FOLDER_INCOME)) ?? $defaults->incomeFolder,
            expenseFolder: $this->nonEmpty($this->providerKey($provider, self::SUFFIX_FOLDER_EXPENSE)) ?? $defaults->expenseFolder,
        );
    }

    public function setGoogleDrive(GoogleDriveConfig $config): void
    {
        $provider = CloudProvider::GoogleDrive;

        $this->put($this->providerKey($provider, self::SUFFIX_ROOT_FOLDER), trim($config->rootFolderId));
        $this->put($this->providerKey($provider, self::SUFFIX_FOLDER_INCOME), trim($config->incomeFolder));
        $this->put($this->providerKey($provider, self::SUFFIX_FOLDER_EXPENSE), trim($config->expenseFolder));
    }

    /**
     * The secret that authorizes the app against one provider -- a refresh token
     * for Drive, keys for an S3-shaped one. It is a long-lived password for the
     * user's whole account, so it is encrypted at rest. The column is `text`,
     * which is what the encrypted payload needs.
     *
     * A `DecryptException` means `APP_KEY` was rotated: the secret is
     * unrecoverable, and reporting it as "not connected" sends the user to
     * reconnect instead of blowing up mid-run.
     */
    public function credential(CloudProvider $provider): ?string
    {
        $stored = $this->nonEmpty($this->providerKey($provider, self::SUFFIX_CREDENTIAL));

        if ($stored === null) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    public function setCredential(CloudProvider $provider, string $credential): void
    {
        $this->put($this->providerKey($provider, self::SUFFIX_CREDENTIAL), Crypt::encryptString($credential));
    }

    public function accountLabel(CloudProvider $provider): ?string
    {
        return $this->nonEmpty($this->providerKey($provider, self::SUFFIX_ACCOUNT));
    }

    /**
     * A blank label forgets the row instead of storing an empty string, so
     * "not known yet" has exactly one representation and `CloudAccount` can
     * tell it apart from a real answer.
     */
    public function setAccountLabel(CloudProvider $provider, string $label): void
    {
        $label = trim($label);
        $key = $this->providerKey($provider, self::SUFFIX_ACCOUNT);

        if ($label === '') {
            $this->forget($key);

            return;
        }

        $this->put($key, $label);
    }

    public function isCloudConnected(CloudProvider $provider): bool
    {
        return $this->credential($provider) !== null;
    }

    /**
     * Forgets the authorization without touching the folder configuration, so
     * reconnecting does not mean setting the templates up again. It is scoped to
     * one provider: disconnecting Drive must not disconnect anything else.
     */
    public function disconnectCloud(CloudProvider $provider): void
    {
        $this->forget($this->providerKey($provider, self::SUFFIX_CREDENTIAL));
        $this->forget($this->providerKey($provider, self::SUFFIX_ACCOUNT));
    }

    private function providerKey(CloudProvider $provider, string $suffix): string
    {
        return "cloud_{$provider->value}_{$suffix}";
    }

    private function nonEmpty(string $key): ?string
    {
        $value = trim((string) $this->get($key));

        return $value === '' ? null : $value;
    }

    private function get(string $key): ?string
    {
        return Setting::query()->where('key', $key)->value('value');
    }

    private function put(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
    }
}
