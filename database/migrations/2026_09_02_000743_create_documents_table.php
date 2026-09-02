<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('counterparty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('folio')->nullable();
            $table->string('extension', 4);
            $table->string('name');
            $table->string('original_name');
            $table->date('issued_on')->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->string('type_letter', 1)->nullable();
            $table->string('confidence');
            $table->string('source')->nullable();

            // `documents` for what the web archived; `null` when the row merely
            // points at a file living on the user's disk (the command).
            $table->string('disk')->nullable();
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            // Deduplication. SQLite treats every NULL as distinct, so CFDIs with
            // no folio (read from the PDF) do not collide with each other: for
            // those the fallback is the checksum.
            $table->unique(['folio', 'extension']);
            $table->index(['checksum', 'extension']);
            $table->index('issued_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
