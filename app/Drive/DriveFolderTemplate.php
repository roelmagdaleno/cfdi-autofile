<?php

namespace App\Drive;

/**
 * The name of a Drive folder, with the year left as a hole to fill.
 *
 * This is deliberately NOT part of the `Placeholder` enum. That catalog is the
 * single source of truth for the file-name template -- the tokens
 * `FileNameBuilder` substitutes, the validation pattern and the buttons on the
 * settings screen all come from it -- and a folder name is a different catalog
 * with exactly one token. Folding them together would offer `{amount}` as a
 * folder name and force `{year}` into file names.
 */
final readonly class DriveFolderTemplate
{
    public const string TOKEN = '{year}';

    public const int MAX_LENGTH = 120;

    public function __construct(public string $template) {}

    public function render(int $year): string
    {
        return str_replace(self::TOKEN, (string) $year, $this->template);
    }

    /**
     * A template without `{year}` would file 2024 and 2026 in the same folder,
     * silently undoing the whole point of the integration, so it is rejected
     * rather than accepted as a constant name.
     */
    public static function isValid(string $template): bool
    {
        $template = trim($template);

        if ($template === '' || mb_strlen($template) > self::MAX_LENGTH) {
            return false;
        }

        if (! str_contains($template, self::TOKEN)) {
            return false;
        }

        // Drive accepts almost any character in a name; a slash is the one
        // that makes the name meaningless, and a backslash reads as an escape.
        return preg_match('/[\/\\\\]/u', $template) !== 1;
    }
}
