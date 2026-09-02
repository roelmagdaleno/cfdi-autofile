<?php

namespace App\Console\Prompts;

use App\Cfdi\Naming\CounterpartyNamePrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Terminal prompt, byte for byte like the one in the Python CLI.
 *
 * It uses raw `fgets` instead of `$this->ask()` for two reasons: Laravel's
 * prompt renders a different shape (`> ` on its own line) and throws on EOF,
 * whereas here Ctrl-C and EOF must fall silently back to the suggestion, just
 * like the original's `except (EOFError, KeyboardInterrupt)`.
 */
final class ConsoleNamePrompt implements CounterpartyNamePrompt
{
    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
    ) {}

    public function askName(string $rfc, ?string $legalName, string $suggestion): string
    {
        $label = $legalName !== null && $legalName !== '' ? " ({$legalName})" : '';

        $this->output->write(
            (string) __('console.prompt.new_counterparty', [
                'rfc' => $rfc,
                'legal' => $label,
                'suggestion' => $suggestion,
            ]),
            false,
            OutputInterface::OUTPUT_RAW,
        );

        $answer = $this->readLine();

        return $answer === null || trim($answer) === '' ? $suggestion : trim($answer);
    }

    /**
     * Reads a line. Returns `null` on EOF or when the user interrupts.
     */
    public function readLine(): ?string
    {
        $stream = STDIN;

        if ($this->input instanceof StreamableInputInterface) {
            $stream = $this->input->getStream() ?? STDIN;
        }

        $restore = $this->silenceInterrupts();

        try {
            $line = fgets($stream);
        } finally {
            $restore();
        }

        return $line === false ? null : $line;
    }

    /**
     * Makes Ctrl-C cut the `fgets` short without killing the process.
     *
     * @return callable(): void
     */
    private function silenceInterrupts(): callable
    {
        if (! extension_loaded('pcntl') || ! function_exists('pcntl_signal')) {
            return static fn () => null;
        }

        $previousAsync = pcntl_async_signals(true);
        pcntl_signal(SIGINT, static fn () => null);

        return static function () use ($previousAsync): void {
            pcntl_signal(SIGINT, SIG_DFL);
            pcntl_async_signals($previousAsync);
        };
    }
}
