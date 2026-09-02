<?php

namespace App\Cloud\Google;

use App\Cfdi\Data\TypeLetter;
use App\Cloud\Contracts\CloudDestinations;
use App\Cloud\Data\CloudDestination;
use App\Cloud\Exceptions\CloudDestinationUnknownException;
use App\Cloud\Google\Contracts\GoogleFolders;

/**
 * Drive's answer to "where does this file go": the id of the year folder it
 * belongs in, created on the spot when the user does not have it yet.
 */
final class GoogleDestinations implements CloudDestinations
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
        private readonly GoogleFolders $folders,
        private readonly GoogleDriveConfig $config,
    ) {}

    /**
     * @throws CloudDestinationUnknownException when there is no folder this file
     *                                          could belong to
     */
    public function destinationFor(TypeLetter $type, ?string $issuedOn): CloudDestination
    {
        $name = $this->folderNameFor($type, $issuedOn);

        return new CloudDestination(
            handle: $this->resolved[$name] ??= $this->folders->ensureFolder($this->config->rootFolderId, $name),
            label: $name,
        );
    }

    private function folderNameFor(TypeLetter $type, ?string $issuedOn): string
    {
        return (new GoogleFolderTemplate($this->templateFor($type)))->render($this->yearOf($issuedOn));
    }

    private function templateFor(TypeLetter $type): string
    {
        return match ($type) {
            TypeLetter::Income => $this->config->incomeFolder,
            TypeLetter::Expense => $this->config->expenseFolder,
            // Neither folder is right: the user's RFC is not on the invoice, so
            // it is not theirs to file as emitted or received.
            TypeLetter::Unknown => throw CloudDestinationUnknownException::unknownType(),
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
            throw CloudDestinationUnknownException::missingDate();
        }

        $year = (int) mb_substr($issuedOn, 0, 4);

        if ($year <= 0) {
            throw CloudDestinationUnknownException::missingDate();
        }

        return $year;
    }
}
