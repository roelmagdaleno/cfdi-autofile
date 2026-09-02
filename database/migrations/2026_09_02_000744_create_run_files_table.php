<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('status');
            $table->string('original_name');
            $table->string('new_name')->nullable();
            $table->text('note')->nullable();

            $table->index(['run_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_files');
    }
};
