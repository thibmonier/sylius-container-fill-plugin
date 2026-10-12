<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

/**
 * Fill rules of a cart. $outcomes: one per applicable rule, on every line of its scope (rules
 * combine: any unmet rule blocks). $plan: the load plan, each line planned once by its most
 * specific rule (container counts for shipping, display); the plan never blocks by itself.
 */
final class CartFillReport
{
    /**
     * @param list<RuleOutcome> $outcomes
     * @param list<Violation> $lineViolations
     * @param list<RuleOutcome> $plan
     */
    public function __construct(
        public readonly array $outcomes = [],
        public readonly array $lineViolations = [],
        public readonly array $plan = [],
    ) {
    }

    /**
     * Whether the containers of this rule outcome are the ones of the displayed load plan. When
     * not (the rule also covers lines planned by a more specific rule), its violations and
     * suggestion number its own containers: show them next to it.
     */
    public function isPlanned(RuleOutcome $outcome): bool
    {
        return \in_array($outcome, $this->plan, true);
    }

    /** Physical containers of the load plan (each line counted once). */
    public function containerCount(): int
    {
        $count = 0;
        foreach ($this->plan as $outcome) {
            $count += $outcome->result->containerCount();
        }

        return $count;
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
        $plan = [];
        foreach ($this->plan as $outcome) {
            $plan[] = [
                'rule' => ['id' => $outcome->rule->id, 'name' => $outcome->rule->name],
                'containerType' => ['code' => $outcome->result->container->code, 'name' => $outcome->result->container->name, 'kind' => $outcome->result->container->kind, 'indicative' => $outcome->result->container->indicative],
                'containerCount' => $outcome->result->containerCount(),
                'containers' => self::containers($outcome),
            ];
        }

        $outcomes = [];
        foreach ($this->outcomes as $outcome) {
            $result = $outcome->result;
            $containers = self::containers($outcome);
            $outcomes[] = [
                'rule' => ['id' => $outcome->rule->id, 'name' => $outcome->rule->name, 'mode' => $outcome->rule->mode, 'threshold' => $outcome->rule->threshold],
                'containerType' => ['code' => $result->container->code, 'name' => $result->container->name, 'kind' => $result->container->kind, 'indicative' => $result->container->indicative],
                'containerCount' => $result->containerCount(),
                'volumeAvailable' => $result->volumeAvailable,
                'containers' => $containers,
                'planned' => $this->isPlanned($outcome),
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
            'containerCount' => $this->containerCount(),
            'plan' => $plan,
            'rules' => $outcomes,
            'lineViolations' => array_map(static fn (Violation $violation): array => ['code' => $violation->code, 'parameters' => $violation->parameters], $this->lineViolations),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function containers(RuleOutcome $outcome): array
    {
        $containers = [];
        foreach ($outcome->result->containers as $container) {
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

        return $containers;
    }
}
