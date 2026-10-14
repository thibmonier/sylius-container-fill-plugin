<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tmonier\SyliusContainerFillPlugin\QuickOrder\CaseQuantityConstraintProvider;

/**
 * tmonier/sylius-quick-order-plugin is optional: its quantity constraint provider is decorated
 * only when the interface and the service exist.
 */
final class QuickOrderBridgePass implements CompilerPassInterface
{
    public const QUICK_ORDER_PROVIDER = 'tmonier_quick_order.quantity_constraint_provider';

    public const QUICK_ORDER_INTERFACE = \Tmonier\SyliusQuickOrderPlugin\Quantity\QuantityConstraintProviderInterface::class;

    public const SERVICE_ID = 'tmonier_container_fill.quick_order.quantity_constraint_provider';

    public function process(ContainerBuilder $container): void
    {
        if (!interface_exists(self::QUICK_ORDER_INTERFACE) || !$container->has(self::QUICK_ORDER_PROVIDER)) {
            return;
        }

        $container->setDefinition(
            self::SERVICE_ID,
            (new Definition(CaseQuantityConstraintProvider::class))
                ->setDecoratedService(self::QUICK_ORDER_PROVIDER)
                ->setArguments([
                    new Reference('tmonier_container_fill.logistics_provider'),
                    new Reference(self::SERVICE_ID . '.inner'),
                ]),
        );
    }
}
