<?php

namespace Database\Factories;

use App\Cfdi\Data\FileStatus;
use App\Models\Run;
use App\Models\RunFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunFile>
 */
class RunFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();

        return [
            'run_id' => Run::factory(),
            'document_id' => null,
            'position' => 0,
            'status' => FileStatus::Ok->value,
            'original_name' => "{$name}.xml",
            'new_name' => '2026-02-14 G Contador - 11600.00.xml',
            'note' => 'copiado',
        ];
    }

    public function rejected(string $reason = 'Rechazado: el XML no es un CFDI.'): static
    {
        return $this->state(fn (): array => [
            'status' => FileStatus::Rejected->value,
            'new_name' => null,
            'note' => $reason,
        ]);
    }
}
