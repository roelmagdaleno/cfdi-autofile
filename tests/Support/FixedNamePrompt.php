<?php

namespace Tests\Support;

use App\Cfdi\Naming\CounterpartyNamePrompt;
use PHPUnit\Framework\Assert;

/**
 * Test prompt. With `$mustNotBeCalled` it reproduces the Python project's
 * `pytest.fail("should not have asked")`.
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
            Assert::fail("should not have asked about {$rfc}");
        }

        $this->calls++;

        return $this->answer;
    }
}
