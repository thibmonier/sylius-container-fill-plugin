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

    /**
     * Fill of the container: the highest of places and weight. The volume (when known) is an
     * indicator only, never the blocking criterion (core dimensions are often rough).
     */
    public function ratio(): float
    {
        return max($this->slotRatio, $this->weightRatio);
    }

    /** The goods may not fit by volume (indicator, does not block). */
    public function volumeExceeded(): bool
    {
        return null !== $this->volumeRatio && $this->volumeRatio > 1 + 1e-6;
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

        return BindingConstraint::Weight;
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
