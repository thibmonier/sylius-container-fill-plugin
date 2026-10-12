<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Logistics;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ProductVariantLogisticsRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\VariantContainerCapacityRepositoryInterface;

/** Two queries for all the variants of a cart. */
final class VariantLogisticsProvider implements VariantLogisticsProviderInterface
{
    public function __construct(
        private readonly ProductVariantLogisticsRepositoryInterface $logisticsRepository,
        private readonly VariantContainerCapacityRepositoryInterface $capacityRepository,
        private readonly Units $units,
    ) {
    }

    public function getForVariants(array $variants): array
    {
        $ids = [];
        foreach ($variants as $variant) {
            if (null !== $variant->getId()) {
                $ids[] = (int) $variant->getId();
            }
        }

        $logistics = [] === $ids ? [] : $this->logisticsRepository->findByVariantIds($ids);
        $capacities = [];
        foreach ([] === $ids ? [] : $this->capacityRepository->findByVariantIds($ids) as $capacity) {
            $this->index($capacities, $capacity);
        }

        $result = [];
        foreach ($variants as $variant) {
            $id = (int) $variant->getId();
            $data = new VariantLogistics($variant, $logistics[$id] ?? null, $capacities[$id] ?? [], $this->units);
            $result[$data->key()] = $data;
        }

        return $result;
    }

    public function getForVariant(ProductVariantInterface $variant): VariantLogistics
    {
        $all = $this->getForVariants([$variant]);

        return reset($all) ?: new VariantLogistics($variant, null, [], $this->units);
    }

    /** @param array<int, array<string, VariantContainerCapacityInterface>> $capacities */
    private function index(array &$capacities, VariantContainerCapacityInterface $capacity): void
    {
        $variantId = $capacity->getVariant()?->getId();
        $code = $capacity->getContainerType()?->getCode();
        if (null !== $variantId && null !== $code) {
            $capacities[(int) $variantId][$code] = $capacity;
        }
    }
}
