<?php

use Illuminate\Support\Facades\DB;

/**
 * Runs only the data migration that rewrites the values persisted before the
 * codebase moved to English.
 */
function runTranslationMigration(string $direction): void
{
    $migration = require database_path('migrations/2026_09_02_033739_translate_spanish_values_to_english.php');

    $migration->{$direction}();
}

it('rewrites the placeholder tokens of a stored template', function (): void {
    DB::table('settings')->insert(['key' => 'template', 'value' => '{fecha} {tipo} {contraparte} - {monto}{nota_credito}']);

    runTranslationMigration('up');

    expect(DB::table('settings')->where('key', 'template')->value('value'))
        ->toBe('{date} {type} {counterparty} - {amount}{credit_note}');
});

it('rewrites the persisted enum values', function (): void {
    $run = createRunWithSpanishValues();

    runTranslationMigration('up');

    expect(DB::table('documents')->where('id', $run['documentId'])->value('confidence'))->toBe('high')
        ->and(DB::table('run_files')->where('run_id', $run['runId'])->pluck('status')->all())
        ->toBe(['ok', 'rejected']);
});

// The note column also holds free-text rejection reasons, so only the exact
// one-word action notes may be touched.
it('rewrites the action note but leaves a rejection reason alone', function (): void {
    $run = createRunWithSpanishValues();

    runTranslationMigration('up');

    expect(DB::table('run_files')->where('run_id', $run['runId'])->pluck('note')->all())
        ->toBe(['copied', 'Este XML no es un CFDI valido: el nodo raiz...']);
});

it('is reversible', function (): void {
    DB::table('settings')->insert(['key' => 'template', 'value' => '{fecha} {tipo} - {monto}']);
    $run = createRunWithSpanishValues();

    runTranslationMigration('up');
    runTranslationMigration('down');

    expect(DB::table('settings')->where('key', 'template')->value('value'))->toBe('{fecha} {tipo} - {monto}')
        ->and(DB::table('documents')->where('id', $run['documentId'])->value('confidence'))->toBe('alta')
        ->and(DB::table('run_files')->where('run_id', $run['runId'])->pluck('status')->all())->toBe(['OK', 'RECHAZADO'])
        ->and(DB::table('run_files')->where('run_id', $run['runId'])->pluck('note')->all())
        ->toBe(['copiado', 'Este XML no es un CFDI valido: el nodo raiz...']);
});

/**
 * @return array{runId: string, documentId: int}
 */
function createRunWithSpanishValues(): array
{
    $documentId = DB::table('documents')->insertGetId([
        'folio' => 'A1B2C3D4', 'extension' => 'xml', 'name' => 'n.xml', 'original_name' => 'o.xml',
        'confidence' => 'alta', 'path' => '/x', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $runId = '11111111-1111-4111-8111-111111111111';

    DB::table('runs')->insert([
        'id' => $runId, 'step' => 'results', 'total_files' => 2, 'ok_files' => 1, 'ignored_files' => 0,
        'warnings' => '[]', 'new_counterparties' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('run_files')->insert([
        ['run_id' => $runId, 'position' => 0, 'status' => 'OK', 'original_name' => 'a.xml', 'note' => 'copiado'],
        ['run_id' => $runId, 'position' => 1, 'status' => 'RECHAZADO', 'original_name' => 'b.xml', 'note' => 'Este XML no es un CFDI valido: el nodo raiz...'],
    ]);

    return ['runId' => $runId, 'documentId' => $documentId];
}
