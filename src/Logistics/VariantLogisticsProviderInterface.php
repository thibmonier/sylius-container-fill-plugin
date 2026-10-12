<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Logistics;

use Sylius\Component\Core\Model\ProductVariantInterface;

interface VariantLogisticsProviderInterface
{
    /**
     * @param list<ProductVariantInterface> $variants
     *
     * @return array<string, VariantLogistics> by variant key (code)
     */
    public function getForVariants(array $variants): array;

    public function getForVariant(ProductVariantInterface $variant): VariantLogistics;
}
