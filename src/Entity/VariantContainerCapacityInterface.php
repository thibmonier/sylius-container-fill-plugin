<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Model\ResourceInterface;

/** How many units of a variant fill one container of a type (the commercial rule, not geometry). */
interface VariantContainerCapacityInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getVariant(): ?ProductVariantInterface;

    public function setVariant(?ProductVariantInterface $variant): void;

    public function getContainerType(): ?ContainerTypeInterface;

    public function setContainerType(?ContainerTypeInterface $containerType): void;

    public function getQuantity(): int;

    public function setQuantity(int $quantity): void;

    /** Units per layer, null when unknown ("round up to the layer" is offered only when set). */
    public function getUnitsPerLayer(): ?int;

    public function setUnitsPerLayer(?int $unitsPerLayer): void;
}
