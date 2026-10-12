<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Component\Core\Model\ProductVariantInterface;

class VariantContainerCapacity implements VariantContainerCapacityInterface
{
    protected ?int $id = null;

    protected ?ProductVariantInterface $variant = null;

    protected ?ContainerTypeInterface $containerType = null;

    protected int $quantity = 1;

    protected ?int $unitsPerLayer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): ?ProductVariantInterface
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariantInterface $variant): void
    {
        $this->variant = $variant;
    }

    public function getContainerType(): ?ContainerTypeInterface
    {
        return $this->containerType;
    }

    public function setContainerType(?ContainerTypeInterface $containerType): void
    {
        $this->containerType = $containerType;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitsPerLayer(): ?int
    {
        return $this->unitsPerLayer;
    }

    public function setUnitsPerLayer(?int $unitsPerLayer): void
    {
        $this->unitsPerLayer = $unitsPerLayer;
    }
}
