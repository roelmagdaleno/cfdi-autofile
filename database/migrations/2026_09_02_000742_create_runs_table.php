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

            // Marca el paso de `cfdi:clean`: los directorios de la corrida ya se
            // vaciaron, pero la fila se queda como historial.
            $table->timestamp('files_purged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
