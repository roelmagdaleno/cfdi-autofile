<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('step')->default('upload');
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('ok_files')->default(0);
            $table->unsignedInteger('ignored_files')->default(0);
            $table->json('warnings');
            $table->json('new_counterparties');

            // Marks that `cfdi:clean` has been through: the run's directories were
            // emptied, but the row stays as history.
            $table->timestamp('files_purged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
