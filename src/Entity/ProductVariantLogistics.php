<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Component\Core\Model\ProductVariantInterface;

class ProductVariantLogistics implements ProductVariantLogisticsInterface
{
    protected ?int $id = null;

    protected ?ProductVariantInterface $variant = null;

    protected int $unitsPerCase = 1;

    protected ?int $caseWeight = null;

    protected bool $stackable = true;

    protected bool $ownPallet = false;

    protected bool $caseMultipleRequired = false;

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

    public function getUnitsPerCase(): int
    {
        return $this->unitsPerCase;
    }

    public function setUnitsPerCase(int $unitsPerCase): void
    {
        $this->unitsPerCase = $unitsPerCase;
    }

    public function getCaseWeight(): ?int
    {
        return $this->caseWeight;
    }

    public function setCaseWeight(?int $caseWeight): void
    {
        $this->caseWeight = $caseWeight;
    }

    public function isStackable(): bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): void
    {
        $this->stackable = $stackable;
    }

    public function isOwnPallet(): bool
    {
        return $this->ownPallet;
    }

    public function setOwnPallet(bool $ownPallet): void
    {
        $this->ownPallet = $ownPallet;
    }

    public function isCaseMultipleRequired(): bool
    {
        return $this->caseMultipleRequired;
    }

    public function setCaseMultipleRequired(bool $caseMultipleRequired): void
    {
        $this->caseMultipleRequired = $caseMultipleRequired;
    }
}
