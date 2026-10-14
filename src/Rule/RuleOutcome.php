<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Planner\FillResult;

/** One applicable rule: the plan of the container type retained, violations, suggestion. */
final readonly class RuleOutcome
{
    /** @param list<Violation> $violations */
    public function __construct(
        public RuleSpec $rule,
        public FillResult $result,
        public array $violations,
        public ?Suggestion $suggestion = null,
    ) {
    }

    public function isSatisfied(): bool
    {
        return [] === $this->violations;
    }
}
