<?php

namespace App\Support\Runs;

use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\FileGrouper;
use App\Cfdi\Naming\CounterpartyResolver;
use App\Cfdi\Naming\PartyResolver;
use App\Cfdi\Parsing\CfdiParserPipeline;
use App\Cfdi\Support\Rfc;

/**
 * Phase A: works out which counterparties have to be asked about, WITHOUT side
 * effects.
 *
 * It writes no files and does not touch the `counterparties` table. Rejections
 * are ignored here: they show up again in phase B, with their reason, through
 * the orchestrator.
 */
final class RunAnalyzer
{
    public function __construct(
        private readonly FileGrouper $grouper,
        private readonly CfdiParserPipeline $parser,
        private readonly CounterpartyResolver $resolver,
    ) {}

    /**
     * @param  list<string>  $paths
     * @return list<UnknownCounterparty>
     */
    public function analyze(array $paths, string $myRfc): array
    {
        /** @var array<string, UnknownCounterparty> $unknown */
        $unknown = [];

        foreach ($this->grouper->group($paths) as $group) {
            try {
                $data = $this->parser->parse($group);
            } catch (CfdiRejectedException) {
                continue;
            }

            $reference = PartyResolver::counterpartyOf($data, $myRfc);
            $rfc = Rfc::normalize($reference->rfc);

            if ($rfc === '' || Rfc::isGeneric($rfc) || isset($unknown[$rfc]) || $this->resolver->isKnown($rfc)) {
                continue;
            }

            $unknown[$rfc] = new UnknownCounterparty(
                $rfc,
                $reference->legalName,
                $this->resolver->suggestionFor($rfc, $reference->legalName),
            );
        }

        return array_values($unknown);
    }
}
