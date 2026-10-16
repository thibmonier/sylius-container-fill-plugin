<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Logistics;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;

/**
 * Logistics data of one variant: plugin data first, Sylius weight and dimensions (raw fields:
 * getShippingVolume() returns 0 when one is missing) as fallback.
 */
final readonly class VariantLogistics
{
    /** @param array<string, VariantContainerCapacityInterface> $capacities by container type code */
    public function __construct(
        public ProductVariantInterface $variant,
        public ?ProductVariantLogisticsInterface $logistics,
        private array $capacities,
        private Units $units,
    ) {
    }

    public function key(): string
    {
        return $this->variant->getCode() ?? ('#' . EntityId::string($this->variant->getId()));
    }

    public function label(): string
    {
        $product = $this->variant->getProduct();
        $productName = $product?->getName() ?? $this->key();
        $variantName = $this->variant->getName();
        if ($product instanceof \Sylius\Component\Product\Model\ProductInterface && $product->hasOptions() && null !== $variantName && '' !== $variantName && $variantName !== $productName) {
            return $productName . ' – ' . $variantName;
        }

        return $productName;
    }

    public function unitsPerCase(): int
    {
        return max(1, $this->logistics?->getUnitsPerCase() ?? 1);
    }

    public function isCaseMultipleRequired(): bool
    {
        return $this->logistics instanceof \Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface && $this->logistics->isCaseMultipleRequired() && $this->unitsPerCase() > 1;
    }

    public function capacityFor(ContainerTypeInterface $type): ?VariantContainerCapacityInterface
    {
        return $this->capacities[(string) $type->getCode()] ?? null;
    }

    /** Units filling one container: its own capacity, or the capacity of its nested type x places. */
    public function unitsPerContainer(ContainerTypeInterface $type): ?int
    {
        $capacity = $this->capacityFor($type);
        if ($capacity instanceof \Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface && $capacity->getQuantity() > 0) {
            return $capacity->getQuantity();
        }
        $nested = $type->getNestedType();
        if ($nested instanceof \Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface && $nested->getCode() !== $type->getCode()) {
            $nestedCapacity = $this->capacityFor($nested);
            if ($nestedCapacity instanceof \Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface && $nestedCapacity->getQuantity() > 0) {
                return $nestedCapacity->getQuantity() * max(1, $type->getSlots());
            }
        }

        return null;
    }

    public function unitsPerLayer(ContainerTypeInterface $type): ?int
    {
        $layer = $this->capacityFor($type)?->getUnitsPerLayer() ?? $this->capacityFor($type->getNestedType() ?? $type)?->getUnitsPerLayer();

        return null !== $layer && $layer > 0 ? $layer : null;
    }

    /** Grams per sale unit, null when unknown. */
    public function unitWeight(): ?float
    {
        $caseWeight = $this->logistics?->getCaseWeight();
        if (null !== $caseWeight && $caseWeight > 0) {
            return $caseWeight / $this->unitsPerCase();
        }
        $weight = $this->variant->getWeight();
        if (null !== $weight && is_finite($weight) && $weight > 0) {
            return $this->units->toGrams($weight);
        }

        return null;
    }

    /** mm³ per sale unit, null when a dimension is missing. */
    public function unitVolume(): ?float
    {
        $width = $this->variant->getWidth();
        $height = $this->variant->getHeight();
        $depth = $this->variant->getDepth();
        if (in_array(null, [$width, $height, $depth], true) || !is_finite($width) || !is_finite($height) || !is_finite($depth) || $width <= 0 || $height <= 0 || $depth <= 0) {
            return null;
        }

        return $this->units->toMillimetres($width) * $this->units->toMillimetres($height) * $this->units->toMillimetres($depth);
    }

    /** A Sylius weight or dimension is not a finite number (corrupted import): worth a warning. */
    public function isCorrupted(): bool
    {
        foreach ([$this->variant->getWeight(), $this->variant->getWidth(), $this->variant->getHeight(), $this->variant->getDepth()] as $value) {
            if (null !== $value && !is_finite($value)) {
                return true;
            }
        }

        return false;
    }

    /** Left out of the fill calculation: no usable unit weight (absent, zero, negative) or corrupted data. */
    public function isIgnored(): bool
    {
        return $this->isCorrupted() || null === $this->unitWeight();
    }

    public function planLine(ContainerTypeInterface $type, int $quantity): PlanLine
    {
        return new PlanLine(
            $this->key(),
            $this->label(),
            $quantity,
            $this->unitsPerContainer($type),
            $this->unitWeight(),
            $this->unitVolume(),
            $this->unitsPerCase(),
            $this->logistics?->isOwnPallet() ?? false,
            $this->unitsPerLayer($type),
            $this->layerHeight(),
            $this->isStackable(),
        );
    }

    /** Height of one layer in mm (layer mode), null when unknown. */
    public function layerHeight(): ?int
    {
        $height = $this->logistics?->getLayerHeight();

        return null !== $height && $height > 0 ? $height : null;
    }

    /** Other layers may be put on top (true without logistics data, as in 1.0). */
    public function isStackable(): bool
    {
        return $this->logistics?->isStackable() ?? true;
    }
}
