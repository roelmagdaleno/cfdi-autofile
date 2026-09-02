<?php

namespace App\Support\Runs;

use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\FileGrouper;
use App\Cfdi\Naming\CounterpartyResolver;
use App\Cfdi\Naming\PartyResolver;
use App\Cfdi\Parsing\CfdiParserPipeline;
use App\Cfdi\Support\Rfc;

/**
 * Fase A: descubre a que contrapartes hay que preguntarle, SIN efectos.
 *
 * No escribe archivos ni toca la tabla `counterparties`. Los rechazos se
 * ignoran aqui: reaparecen en la fase B, ya con su motivo, via el orquestador.
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
