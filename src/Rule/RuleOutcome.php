<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Planner\FillResult;

/** One applicable rule: the plan of the container type retained, violations, suggestion. */
final class RuleOutcome
{
    /** @param list<Violation> $violations */
    public function __construct(
        public readonly RuleSpec $rule,
        public readonly FillResult $result,
        public readonly array $violations,
        public readonly ?Suggestion $suggestion = null,
    ) {
    }

    public function isSatisfied(): bool
    {
        return [] === $this->violations;
    }
}
