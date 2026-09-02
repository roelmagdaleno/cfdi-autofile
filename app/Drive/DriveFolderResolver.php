<?php

namespace App\Drive;

use App\Cfdi\Data\TypeLetter;
use App\Drive\Contracts\DriveFiles;
use App\Drive\Data\DriveConfig;
use App\Drive\Exceptions\DriveDestinationUnknownException;

/**
 * Turns "what kind of CFDI is this, and when was it issued" into the id of the
 * Drive folder it belongs in.
 *
 * The two coordinates are the ones the processor already produces: the issue
 * date gives the year, and `TypeLetter` says which of the two folders of that
 * year -- income means the user's RFC is the issuer, so the invoice was one they
 * emitted.
 */
final class DriveFolderResolver
{
    /**
     * Folder name => Drive id, for the lifetime of one sync.
     *
     * Without it a run of twenty invoices would ask Drive to look up the same
     * two folders twenty times.
     *
     * @var array<string, string>
     */
    private array $resolved = [];

    public function __construct(
        private readonly DriveFiles $files,
        private readonly DriveConfig $config,
    ) {}

    /**
     * @throws DriveDestinationUnknownException when there is no folder this file
     *                                          could belong to
     */
    public function folderIdFor(TypeLetter $type, ?string $issuedOn): string
    {
        $name = $this->folderNameFor($type, $issuedOn);

        return $this->resolved[$name] ??= $this->files->ensureFolder($this->config->rootFolderId, $name);
    }

    public function folderNameFor(TypeLetter $type, ?string $issuedOn): string
    {
        return (new DriveFolderTemplate($this->templateFor($type)))->render($this->yearOf($issuedOn));
    }

    private function templateFor(TypeLetter $type): string
    {
        return match ($type) {
            TypeLetter::Income => $this->config->incomeFolder,
            TypeLetter::Expense => $this->config->expenseFolder,
            // Neither folder is right: the user's RFC is not on the invoice, so
            // it is not theirs to file as emitted or received.
            TypeLetter::Unknown => throw DriveDestinationUnknownException::unknownType(),
        };
    }

    /**
     * `issuedOn` arrives as the CFDI's `Fecha`, which is ISO-ish and always
     * starts with the year. A CFDI with no date is a PDF read by heuristics that
     * failed that gate, and there is no year to guess.
     */
    private function yearOf(?string $issuedOn): int
    {
        if ($issuedOn === null || mb_strlen($issuedOn) < 4) {
            throw DriveDestinationUnknownException::missingDate();
        }

        $year = (int) mb_substr($issuedOn, 0, 4);

        if ($year <= 0) {
            throw DriveDestinationUnknownException::missingDate();
        }

        return $year;
    }
}
