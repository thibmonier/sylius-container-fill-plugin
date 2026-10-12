<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;

/** @extends RepositoryInterface<ProductVariantLogisticsInterface> */
interface ProductVariantLogisticsRepositoryInterface extends RepositoryInterface
{
    public function findOneByVariant(ProductVariantInterface $variant): ?ProductVariantLogisticsInterface;

    /**
     * @param list<int> $variantIds
     *
     * @return array<int, ProductVariantLogisticsInterface> by variant id
     */
    public function findByVariantIds(array $variantIds): array;
}
