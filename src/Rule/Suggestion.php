<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;

/** "Add 12 units of X to complete container 2", verified against the planner. */
final readonly class Suggestion
{
    public const COMPLETE = 'complete';

    public const ROUND_TO_MULTIPLE = 'round_to_multiple';

    public function __construct(
        public string $action,
        public string $lineKey,
        public string $label,
        public int $addUnits,
        public int $containerIndex,
        public BindingConstraint $binding,
        public int $unitsPerCase = 1,
    ) {
    }

    /** Whole cases added, null when the units are not a whole number of cases. */
    public function addCases(): ?int
    {
        return $this->unitsPerCase > 1 && 0 === $this->addUnits % $this->unitsPerCase ? intdiv($this->addUnits, $this->unitsPerCase) : null;
    }
}
