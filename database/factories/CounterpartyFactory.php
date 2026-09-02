<?php

namespace Database\Factories;

use App\Models\Counterparty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Counterparty>
 */
class CounterpartyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `words()` is typed as array|string, so the text is built with `word()`,
        // which always returns a string.
        $words = fn (int $count): string => Str::upper(
            implode(' ', array_map(fn (): string => fake()->word(), range(1, $count))),
        );

        return [
            // Company RFC format: 3 letters + 6 date digits + 3 of the homoclave.
            'rfc' => Str::upper(Str::random(3)).'010101'.fake()->unique()->numerify('###'),
            'name' => $words(2),
            'legal_name' => $words(3).' SA DE CV',
        ];
    }
}
