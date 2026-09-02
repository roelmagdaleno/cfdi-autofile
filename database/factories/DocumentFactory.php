<?php

namespace Database\Factories;

use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Data\TypeLetter;
use App\Models\Counterparty;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedOn = fake()->dateTimeBetween('-2 years');
        $name = $issuedOn->format('Y-m-d').' G Contador - 11600.00';

        return [
            'counterparty_id' => Counterparty::factory(),
            'folio' => Str::lower((string) Str::uuid()),
            'extension' => 'xml',
            'name' => "{$name}.xml",
            'original_name' => 'factura.xml',
            'issued_on' => $issuedOn->format('Y-m-d'),
            'total' => '11600.00',
            'type_letter' => TypeLetter::Expense->value,
            'confidence' => Confidence::High->value,
            'source' => SourceFormat::Xml->value,
            'disk' => Document::DISK,
            'path' => "ABC010101AB1/{$issuedOn->format('Y')}/{$name}.xml",
            'size' => fake()->numberBetween(2_000, 20_000),
            'checksum' => hash('sha256', (string) Str::uuid()),
        ];
    }

    /**
     * Una fila del comando: apunta a un archivo del disco del usuario, sin copia.
     */
    public function indexedInPlace(string $path): static
    {
        return $this->state(fn (): array => ['disk' => null, 'path' => $path]);
    }
}
