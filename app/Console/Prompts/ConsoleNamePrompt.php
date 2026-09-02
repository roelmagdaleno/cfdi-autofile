<?php

namespace App\Console\Prompts;

use App\Cfdi\Naming\CounterpartyNamePrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prompt de terminal, byte a byte como el del CLI de Python.
 *
 * Se usa `fgets` en crudo y no `$this->ask()` por dos razones: el prompt de
 * Laravel renderiza otra forma (`> ` en su propia linea) y lanza excepcion al
 * llegar a EOF, mientras que aqui Ctrl-C y EOF deben caer silenciosamente en la
 * sugerencia, igual que el `except (EOFError, KeyboardInterrupt)` del original.
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
            "Contraparte nueva {$rfc}{$label}\n  Nombre a usar [{$suggestion}]: ",
            false,
            OutputInterface::OUTPUT_RAW,
        );

        $answer = $this->readLine();

        return $answer === null || trim($answer) === '' ? $suggestion : trim($answer);
    }

    /**
     * Lee una linea. Devuelve `null` en EOF o si el usuario interrumpe.
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
     * Hace que Ctrl-C corte el `fgets` sin matar el proceso.
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
