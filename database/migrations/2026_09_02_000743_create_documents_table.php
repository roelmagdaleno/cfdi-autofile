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

            // `documents` para lo archivado por la web; `null` cuando la fila solo
            // apunta a un archivo que vive en el disco del usuario (el comando).
            $table->string('disk')->nullable();
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            // Deduplicacion. SQLite trata cada NULL como distinto, asi que los
            // CFDIs sin folio (leidos del PDF) no chocan entre si: para esos el
            // fallback es el checksum.
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
