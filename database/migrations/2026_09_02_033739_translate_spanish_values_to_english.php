<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites the Spanish values that were persisted before the codebase moved to
 * English: the placeholder tokens inside saved templates, and the backing values
 * of the `Confidence`, `FileStatus` and `FileAction` enums.
 */
return new class extends Migration
{
    /**
     * Placeholder tokens as they appear inside `settings.value`.
     *
     * @var array<string, string>
     */
    private const array TEMPLATE_TOKENS = [
        '{fecha}' => '{date}',
        '{tipo}' => '{type}',
        '{contraparte}' => '{counterparty}',
        '{monto}' => '{amount}',
        '{nota_credito}' => '{credit_note}',
    ];

    /** @var array<string, string> */
    private const array CONFIDENCE = ['alta' => 'high', 'baja' => 'low'];

    /** @var array<string, string> */
    private const array STATUS = ['OK' => 'ok', 'REVISAR' => 'review', 'RECHAZADO' => 'rejected'];

    /**
     * `run_files.note` also holds free-text rejection reasons, so only the exact
     * one-word action notes are rewritten. Historical rejection notes are left
     * as they were written.
     *
     * @var array<string, string>
     */
    private const array NOTES = ['copiado' => 'copied', 'movido' => 'moved'];

    public function up(): void
    {
        $this->rewrite(self::TEMPLATE_TOKENS, self::CONFIDENCE, self::STATUS, self::NOTES);
    }

    public function down(): void
    {
        $this->rewrite(
            array_flip(self::TEMPLATE_TOKENS),
            array_flip(self::CONFIDENCE),
            array_flip(self::STATUS),
            array_flip(self::NOTES),
        );
    }

    /**
     * @param  array<string, string>  $tokens
     * @param  array<string, string>  $confidence
     * @param  array<string, string>  $status
     * @param  array<string, string>  $notes
     */
    private function rewrite(array $tokens, array $confidence, array $status, array $notes): void
    {
        $templates = DB::table('settings')->where('key', 'template')->get(['id', 'value']);

        foreach ($templates as $template) {
            if (! is_string($template->value)) {
                continue;
            }

            DB::table('settings')
                ->where('id', $template->id)
                ->update(['value' => str_replace(array_keys($tokens), array_values($tokens), $template->value)]);
        }

        foreach ($confidence as $from => $to) {
            DB::table('documents')->where('confidence', $from)->update(['confidence' => $to]);
        }

        foreach ($status as $from => $to) {
            DB::table('run_files')->where('status', $from)->update(['status' => $to]);
        }

        foreach ($notes as $from => $to) {
            DB::table('run_files')->where('note', $from)->update(['note' => $to]);
        }
    }
};
