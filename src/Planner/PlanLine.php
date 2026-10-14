<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/**
 * One variant of the cart for one container type: quantities in sale units, capacity = units
 * filling one container, unit weight in grams, unit volume in mm³.
 */
final readonly class PlanLine
{
    public const MISSING_CAPACITY = 'capacity';

    public const MISSING_WEIGHT = 'weight';

    public function __construct(
        public string $key,
        public string $label,
        public int $quantity,
        public ?int $capacity,
        public ?float $unitWeight,
        public ?float $unitVolume = null,
        public int $unitsPerCase = 1,
        public bool $ownPallet = false,
        public ?int $unitsPerLayer = null,
        /** Height of one layer of the product in mm (layer mode). */
        public ?int $layerHeight = null,
        /** Other layers may be put on top of this product (layer mode). */
        public bool $stackable = true,
    ) {
        if ($quantity < 0 || $unitsPerCase < 1) {
            throw new \InvalidArgumentException('Invalid plan line.');
        }
    }

    /** @return list<string> */
    public function missingData(): array
    {
        $missing = [];
        if (null === $this->capacity || $this->capacity < 1) {
            $missing[] = self::MISSING_CAPACITY;
        }
        if (null === $this->unitWeight || $this->unitWeight <= 0.0) {
            $missing[] = self::MISSING_WEIGHT;
        }

        return $missing;
    }

    public function withQuantity(int $quantity): self
    {
        return new self($this->key, $this->label, $quantity, $this->capacity, $this->unitWeight, $this->unitVolume, $this->unitsPerCase, $this->ownPallet, $this->unitsPerLayer, $this->layerHeight, $this->stackable);
    }

    /** Units per layer and layer height are known (layer mode). */
    public function hasLayerData(): bool
    {
        return null !== $this->unitsPerLayer && $this->unitsPerLayer > 0 && null !== $this->layerHeight && $this->layerHeight > 0;
    }

    /** Share of one container taken by one unit, by places. */
    public function slotShare(): float
    {
        return null === $this->capacity || $this->capacity < 1 ? 0.0 : 1 / $this->capacity;
    }
}
