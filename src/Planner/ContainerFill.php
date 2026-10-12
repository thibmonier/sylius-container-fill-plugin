<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/** One container of a plan: fill ratios (1.0 = 100 %) and units per line key. */
final class ContainerFill
{
    private const EPSILON = 1e-9;

    /** @param array<string, int> $units */
    public function __construct(
        public readonly int $index,
        public readonly float $slotRatio,
        public readonly float $weightRatio,
        public readonly ?float $volumeRatio,
        public readonly array $units,
        public readonly bool $full,
        public readonly ?string $dedicatedTo = null,
    ) {
    }

    /** Fill of the container: the highest of places, weight and volume (when known). */
    public function ratio(): float
    {
        return max($this->slotRatio, $this->weightRatio, $this->volumeRatio ?? 0.0);
    }

    public function percent(): int
    {
        return (int) floor($this->ratio() * 100 + self::EPSILON);
    }

    public function binding(): BindingConstraint
    {
        $ratio = $this->ratio();
        if ($this->slotRatio >= $ratio - self::EPSILON) {
            return BindingConstraint::Slots;
        }
        if ($this->weightRatio >= $ratio - self::EPSILON) {
            return BindingConstraint::Weight;
        }

        return BindingConstraint::Volume;
    }

    public function isOverloaded(): bool
    {
        return $this->ratio() > 1 + 1e-6;
    }

    public function unitCount(): int
    {
        return array_sum($this->units);
    }
}
