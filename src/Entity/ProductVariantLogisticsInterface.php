<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Logistics data of a variant (one per variant, plugin table): the variant is sold per unit,
 * a case holds unitsPerCase units.
 */
interface ProductVariantLogisticsInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getVariant(): ?ProductVariantInterface;

    public function setVariant(?ProductVariantInterface $variant): void;

    public function getUnitsPerCase(): int;

    public function setUnitsPerCase(int $unitsPerCase): void;

    /** Weight of a full case in grams, null: the weight of the variant (Sylius field) x units per case. */
    public function getCaseWeight(): ?int;

    public function setCaseWeight(?int $caseWeight): void;

    public function isStackable(): bool;

    public function setStackable(bool $stackable): void;

    /** Never mixed with other products: whole pallets (places) of its own. */
    public function isOwnPallet(): bool;

    public function setOwnPallet(bool $ownPallet): void;

    /** Cart lines must be multiples of the case (checked when the cart is saved, in the quick order too). */
    public function isCaseMultipleRequired(): bool;

    public function setCaseMultipleRequired(bool $caseMultipleRequired): void;
}
