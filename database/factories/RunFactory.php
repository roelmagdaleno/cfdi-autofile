<?php

namespace Database\Factories;

use App\Models\Run;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'step' => Run::STEP_RESULTS,
            'total_files' => 0,
            'ok_files' => 0,
            'ignored_files' => 0,
            'warnings' => [],
            'new_counterparties' => [],
            'files_purged_at' => null,
        ];
    }

    public function purged(): static
    {
        return $this->state(fn (): array => ['files_purged_at' => now()]);
    }
}
