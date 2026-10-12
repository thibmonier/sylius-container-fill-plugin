<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;

/** "Add 12 units of X to complete container 2", verified against the planner. */
final class Suggestion
{
    public const COMPLETE = 'complete';

    public const ROUND_TO_MULTIPLE = 'round_to_multiple';

    public function __construct(
        public readonly string $action,
        public readonly string $lineKey,
        public readonly string $label,
        public readonly int $addUnits,
        public readonly int $containerIndex,
        public readonly BindingConstraint $binding,
        public readonly int $unitsPerCase = 1,
    ) {
    }

    /** Whole cases added, null when the units are not a whole number of cases. */
    public function addCases(): ?int
    {
        return $this->unitsPerCase > 1 && 0 === $this->addUnits % $this->unitsPerCase ? intdiv($this->addUnits, $this->unitsPerCase) : null;
    }
}
