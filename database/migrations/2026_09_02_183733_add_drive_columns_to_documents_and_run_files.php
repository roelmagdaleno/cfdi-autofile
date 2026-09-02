<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each file landed in Google Drive.
 *
 * The information is recorded in both tables on purpose, and it is not
 * redundant:
 *
 * - `documents` holds the CFDI's lasting identity. `drive_file_id` is what makes
 *   reprocessing the same folio UPDATE the file in Drive instead of adding a
 *   second copy of it.
 * - `run_files` is the immutable snapshot of one run, which is why it already
 *   duplicates `new_name`. It has to be able to say "this file was skipped
 *   because it had no date" for that run, forever, even after the document was
 *   later uploaded successfully.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('drive_file_id')->nullable()->after('checksum');
            $table->string('drive_link')->nullable()->after('drive_file_id');
            $table->timestamp('drive_uploaded_at')->nullable()->after('drive_link');
        });

        Schema::table('run_files', function (Blueprint $table): void {
            $table->string('drive_status')->nullable()->after('note');
            $table->string('drive_link')->nullable()->after('drive_status');
            $table->text('drive_note')->nullable()->after('drive_link');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['drive_file_id', 'drive_link', 'drive_uploaded_at']);
        });

        Schema::table('run_files', function (Blueprint $table): void {
            $table->dropColumn(['drive_status', 'drive_link', 'drive_note']);
        });
    }
};
