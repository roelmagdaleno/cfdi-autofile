<?php

namespace App\Support;

use App\Cfdi\Naming\TypeLetters;
use App\Models\Setting;

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
}
