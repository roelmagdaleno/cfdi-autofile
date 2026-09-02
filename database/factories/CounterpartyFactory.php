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
        // `words()` esta tipado como array|string, asi que armamos el texto con
        // `word()`, que siempre devuelve string.
        $words = fn (int $count): string => Str::upper(
            implode(' ', array_map(fn (): string => fake()->word(), range(1, $count))),
        );

        return [
            // Formato de RFC moral: 3 letras + 6 digitos de fecha + 3 de homoclave.
            'rfc' => Str::upper(Str::random(3)).'010101'.fake()->unique()->numerify('###'),
            'name' => $words(2),
            'legal_name' => $words(3).' SA DE CV',
        ];
    }
}
