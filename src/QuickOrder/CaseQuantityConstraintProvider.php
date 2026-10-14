<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\QuickOrder;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;
use Tmonier\SyliusQuickOrderPlugin\Quantity\QuantityConstraint;
use Tmonier\SyliusQuickOrderPlugin\Quantity\QuantityConstraintProviderInterface;

/**
 * Bridge for tmonier/sylius-quick-order-plugin (registered only when it is installed, see
 * QuickOrderBridgePass): quick order lines and the order matrix follow the case multiples of
 * this plugin. Decorates the quick order provider, which answers for the other variants.
 */
final readonly class CaseQuantityConstraintProvider implements QuantityConstraintProviderInterface
{
    public function __construct(
        private VariantLogisticsProviderInterface $logisticsProvider,
        private ?QuantityConstraintProviderInterface $inner = null,
    ) {
    }

    public function getConstraint(ProductVariantInterface $variant, ChannelInterface $channel): ?QuantityConstraint
    {
        $logistics = $this->logisticsProvider->getForVariant($variant);
        if (!$logistics->isCaseMultipleRequired()) {
            return $this->inner?->getConstraint($variant, $channel);
        }

        return new QuantityConstraint($logistics->unitsPerCase(), $logistics->unitsPerCase());
    }
}
