<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each file landed in the cloud.
 *
 * The information is recorded in two shapes on purpose, and it is not
 * redundant:
 *
 * - `document_cloud_files` is the CFDI's lasting identity in the cloud, one row
 *   per (document, provider). `remote_id` is what makes reprocessing the same
 *   folio REPLACE the remote copy instead of adding a second one, and keeping
 *   it in a table of its own is what lets a document live in two providers at
 *   once -- and what makes switching platforms keep, rather than overwrite, the
 *   ids of the old one.
 * - `run_files` is the immutable snapshot of one run, which is why it already
 *   duplicates `new_name`. It has to be able to say "this file was skipped
 *   because it had no date, and it was Google Drive that skipped it" for that
 *   run, forever, even after the document was uploaded successfully or moved to
 *   another provider. That is why the provider is denormalized onto the row
 *   instead of joined through `document_cloud_files`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_cloud_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // Backing value of a `CloudProvider`.
            $table->string('provider');
            // Opaque to everyone but the provider: a Drive file id, an S3 key,
            // a Dropbox path.
            $table->string('remote_id');
            $table->string('link');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            // One live copy per document per provider. This is the constraint
            // the sync writes against, so idempotence is the database's promise
            // rather than the code's good intentions.
            $table->unique(['document_id', 'provider']);
        });

        Schema::table('run_files', function (Blueprint $table): void {
            $table->string('cloud_provider')->nullable()->after('note');
            $table->string('cloud_status')->nullable()->after('cloud_provider');
            $table->string('cloud_link')->nullable()->after('cloud_status');
            $table->text('cloud_note')->nullable()->after('cloud_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_cloud_files');

        Schema::table('run_files', function (Blueprint $table): void {
            $table->dropColumn(['cloud_provider', 'cloud_status', 'cloud_link', 'cloud_note']);
        });
    }
};
