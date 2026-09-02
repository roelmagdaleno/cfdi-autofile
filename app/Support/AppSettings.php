<?php

namespace App\Support;

use App\Cfdi\Naming\TypeLetters;
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

    public const string KEY_DRIVE_ENABLED = 'drive_enabled';

    public const string KEY_DRIVE_ROOT_FOLDER = 'drive_root_folder_id';

    public const string KEY_DRIVE_FOLDER_INCOME = 'drive_folder_income';

    public const string KEY_DRIVE_FOLDER_EXPENSE = 'drive_folder_expense';

    public const string KEY_DRIVE_REFRESH_TOKEN = 'drive_refresh_token';

    public const string KEY_DRIVE_ACCOUNT_EMAIL = 'drive_account_email';

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
     * The editable half of the Drive integration.
     *
     * Every field falls back to its default, so an install that never opened
     * the Drive card behaves exactly like one that predates the feature: the
     * integration is off and nothing reaches the network.
     */
    public function drive(): GoogleDriveConfig
    {
        $defaults = GoogleDriveConfig::defaults();

        return new GoogleDriveConfig(
            enabled: $this->get(self::KEY_DRIVE_ENABLED) === '1',
            rootFolderId: $this->nonEmpty(self::KEY_DRIVE_ROOT_FOLDER) ?? $defaults->rootFolderId,
            incomeFolder: $this->nonEmpty(self::KEY_DRIVE_FOLDER_INCOME) ?? $defaults->incomeFolder,
            expenseFolder: $this->nonEmpty(self::KEY_DRIVE_FOLDER_EXPENSE) ?? $defaults->expenseFolder,
        );
    }

    /**
     * The credentials are deliberately NOT part of this: a form submission must
     * never be able to blank out the refresh token.
     */
    public function setDrive(GoogleDriveConfig $config): void
    {
        $this->put(self::KEY_DRIVE_ENABLED, $config->enabled ? '1' : '0');
        $this->put(self::KEY_DRIVE_ROOT_FOLDER, trim($config->rootFolderId));
        $this->put(self::KEY_DRIVE_FOLDER_INCOME, trim($config->incomeFolder));
        $this->put(self::KEY_DRIVE_FOLDER_EXPENSE, trim($config->expenseFolder));
    }

    /**
     * A refresh token is a long-lived password for the user's whole Drive, so it
     * is encrypted at rest. The column is `text`, which is what the encrypted
     * payload needs.
     *
     * A `DecryptException` means `APP_KEY` was rotated: the token is
     * unrecoverable, and reporting it as "not connected" sends the user to
     * reconnect instead of blowing up mid-run.
     */
    public function driveRefreshToken(): ?string
    {
        $stored = $this->nonEmpty(self::KEY_DRIVE_REFRESH_TOKEN);

        if ($stored === null) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    public function setDriveRefreshToken(string $token): void
    {
        $this->put(self::KEY_DRIVE_REFRESH_TOKEN, Crypt::encryptString($token));
    }

    public function driveAccountEmail(): ?string
    {
        return $this->nonEmpty(self::KEY_DRIVE_ACCOUNT_EMAIL);
    }

    /**
     * A blank address forgets the row instead of storing an empty string, so
     * "not known yet" has exactly one representation and `CloudAccount` can
     * tell it apart from a real answer.
     */
    public function setDriveAccountEmail(string $email): void
    {
        $email = trim($email);

        if ($email === '') {
            $this->forget(self::KEY_DRIVE_ACCOUNT_EMAIL);

            return;
        }

        $this->put(self::KEY_DRIVE_ACCOUNT_EMAIL, $email);
    }

    public function isDriveConnected(): bool
    {
        return $this->driveRefreshToken() !== null;
    }

    /**
     * Forgets the authorization without touching the folder configuration, so
     * reconnecting does not mean setting the templates up again.
     */
    public function disconnectDrive(): void
    {
        $this->forget(self::KEY_DRIVE_REFRESH_TOKEN);
        $this->forget(self::KEY_DRIVE_ACCOUNT_EMAIL);
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
