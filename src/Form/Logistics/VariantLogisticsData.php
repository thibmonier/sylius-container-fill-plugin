<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Logistics;

/** Data of the "Logistics" tab of the variant form (plugin tables, not the variant). */
final class VariantLogisticsData
{
    public ?int $unitsPerCase = 1;

    /** Grams. */
    public ?int $caseWeight = null;

    public bool $stackable = true;

    /** Millimetres (layer mode). */
    public ?int $layerHeight = null;

    public bool $ownPallet = false;

    public bool $caseMultipleRequired = false;

    /** @var array<string, array{quantity: ?int, unitsPerLayer: ?int}> by "t<container type id>" */
    public array $capacities = [];

    public function isEmpty(): bool
    {
        if ((null !== $this->unitsPerCase && 1 !== $this->unitsPerCase) || null !== $this->caseWeight || !$this->stackable || null !== $this->layerHeight || $this->ownPallet || $this->caseMultipleRequired) {
            return false;
        }
        foreach ($this->capacities as $capacity) {
            if (null !== ($capacity['quantity'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
