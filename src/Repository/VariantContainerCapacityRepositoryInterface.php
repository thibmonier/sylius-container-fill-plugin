<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;

/** @extends RepositoryInterface<VariantContainerCapacityInterface> */
interface VariantContainerCapacityRepositoryInterface extends RepositoryInterface
{
    /**
     * @param list<int> $variantIds
     *
     * @return list<VariantContainerCapacityInterface> with their container type
     */
    public function findByVariantIds(array $variantIds): array;

    /** @return list<VariantContainerCapacityInterface> */
    public function findByVariant(ProductVariantInterface $variant): array;

    public function findOneByVariantAndType(ProductVariantInterface $variant, ContainerTypeInterface $type): ?VariantContainerCapacityInterface;

    /** @return list<VariantContainerCapacityInterface> every capacity with variant and type, by variant code (CSV export) */
    public function findAllForExport(): array;
}
