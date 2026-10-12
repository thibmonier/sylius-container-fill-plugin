<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

/** Fill rules of a cart: one outcome per applicable rule plus the "multiple of the case" lines. */
final class CartFillReport
{
    /**
     * @param list<RuleOutcome> $outcomes
     * @param list<Violation> $lineViolations
     */
    public function __construct(
        public readonly array $outcomes = [],
        public readonly array $lineViolations = [],
    ) {
    }

    public function isSatisfied(): bool
    {
        if ([] !== $this->lineViolations) {
            return false;
        }
        foreach ($this->outcomes as $outcome) {
            if (!$outcome->isSatisfied()) {
                return false;
            }
        }

        return true;
    }

    public function hasRules(): bool
    {
        return [] !== $this->outcomes;
    }

    /** @return list<Violation> */
    public function violations(): array
    {
        $violations = $this->lineViolations;
        foreach ($this->outcomes as $outcome) {
            array_push($violations, ...$outcome->violations);
        }

        return $violations;
    }

    /** @return array<string, mixed> plain data (snapshot, API) */
    public function toArray(): array
    {
        $outcomes = [];
        foreach ($this->outcomes as $outcome) {
            $result = $outcome->result;
            $containers = [];
            foreach ($result->containers as $container) {
                $containers[] = [
                    'index' => $container->index,
                    'percent' => $container->percent(),
                    'slots' => round($container->slotRatio * 100, 1),
                    'weight' => round($container->weightRatio * 100, 1),
                    'volume' => null === $container->volumeRatio ? null : round($container->volumeRatio * 100, 1),
                    'binding' => $container->binding()->value,
                    'full' => $container->full,
                    'units' => $container->units,
                ];
            }
            $outcomes[] = [
                'rule' => ['id' => $outcome->rule->id, 'name' => $outcome->rule->name, 'mode' => $outcome->rule->mode, 'threshold' => $outcome->rule->threshold],
                'containerType' => ['code' => $result->container->code, 'name' => $result->container->name, 'kind' => $result->container->kind, 'indicative' => $result->container->indicative],
                'containerCount' => $result->containerCount(),
                'volumeAvailable' => $result->volumeAvailable,
                'containers' => $containers,
                'satisfied' => $outcome->isSatisfied(),
                'violations' => array_map(static fn (Violation $violation): array => ['code' => $violation->code, 'parameters' => $violation->parameters], $outcome->violations),
                'suggestion' => null === $outcome->suggestion ? null : [
                    'action' => $outcome->suggestion->action,
                    'variant' => $outcome->suggestion->lineKey,
                    'addUnits' => $outcome->suggestion->addUnits,
                    'container' => $outcome->suggestion->containerIndex,
                    'binding' => $outcome->suggestion->binding->value,
                ],
            ];
        }

        return [
            'satisfied' => $this->isSatisfied(),
            'rules' => $outcomes,
            'lineViolations' => array_map(static fn (Violation $violation): array => ['code' => $violation->code, 'parameters' => $violation->parameters], $this->lineViolations),
        ];
    }
}
