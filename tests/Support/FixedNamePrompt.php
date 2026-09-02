<?php

namespace Tests\Support;

use App\Cfdi\Naming\CounterpartyNamePrompt;
use PHPUnit\Framework\Assert;

/**
 * Prompt de prueba. Con `$mustNotBeCalled` reproduce el
 * `pytest.fail("no debio preguntar")` del proyecto de Python.
 */
final class FixedNamePrompt implements CounterpartyNamePrompt
{
    public int $calls = 0;

    public function __construct(
        private readonly string $answer = '',
        private readonly bool $mustNotBeCalled = false,
    ) {}

    public static function failing(): self
    {
        return new self(mustNotBeCalled: true);
    }

    public function askName(string $rfc, ?string $legalName, string $suggestion): string
    {
        if ($this->mustNotBeCalled) {
            Assert::fail("no debio preguntar por {$rfc}");
        }

        $this->calls++;

        return $this->answer;
    }
}
