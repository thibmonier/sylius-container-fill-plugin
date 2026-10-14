<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/** One container of a plan: fill ratios (1.0 = 100 %) and units per line key. */
final readonly class ContainerFill
{
    private const EPSILON = 1e-9;

    /**
     * @param array<string, int> $units
     * @param list<array{key: string, label: string, units: int, unitsPerLayer: int, height: int}> $layers
     */
    public function __construct(
        public int $index,
        public float $slotRatio,
        public float $weightRatio,
        public ?float $volumeRatio,
        public array $units,
        public bool $full,
        public ?string $dedicatedTo = null,
        /** Layer mode: share of the stack height / layer count used. */
        public ?float $layerRatio = null,
        /** Layer mode: layers from the bottom (a partial layer has units < unitsPerLayer). */
        public array $layers = [],
        /** Layer mode: stack height left in mm (null without height limit). */
        public ?int $remainingHeight = null,
    ) {
    }

    /**
     * Fill of the container: the highest of places, weight and (layer mode) stack height. The
     * volume (when known) is an indicator only, never the blocking criterion.
     */
    public function ratio(): float
    {
        return max($this->slotRatio, $this->weightRatio, $this->layerRatio ?? 0.0);
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
        if (null !== $this->layerRatio && $this->layerRatio >= $ratio - self::EPSILON) {
            return BindingConstraint::Layers;
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

    /** Layer mode: a layer is not complete. */
    public function hasPartialLayer(): bool
    {
        foreach ($this->layers as $layer) {
            if ($layer['units'] < $layer['unitsPerLayer']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Layer mode: consecutive identical layers grouped, from the bottom (side view legend).
     *
     * @return list<array{key: string, label: string, count: int, units: int, unitsPerLayer: int}>
     */
    public function layerGroups(): array
    {
        $groups = [];
        foreach ($this->layers as $layer) {
            $last = array_key_last($groups);
            if (null !== $last && $groups[$last]['key'] === $layer['key'] && $groups[$last]['units'] === $layer['units']) {
                ++$groups[$last]['count'];

                continue;
            }
            $groups[] = ['key' => $layer['key'], 'label' => $layer['label'], 'count' => 1, 'units' => $layer['units'], 'unitsPerLayer' => $layer['unitsPerLayer']];
        }

        return $groups;
    }
}
