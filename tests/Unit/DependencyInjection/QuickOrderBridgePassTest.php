<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tmonier\SyliusContainerFillPlugin\DependencyInjection\Compiler\QuickOrderBridgePass;
use Tmonier\SyliusQuickOrderPlugin\Quantity\NullQuantityConstraintProvider;

final class QuickOrderBridgePassTest extends TestCase
{
    public function testDecoratesTheQuickOrderProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(QuickOrderBridgePass::QUICK_ORDER_PROVIDER, new Definition(NullQuantityConstraintProvider::class));

        (new QuickOrderBridgePass())->process($container);

        self::assertTrue($container->hasDefinition(QuickOrderBridgePass::SERVICE_ID));
        self::assertSame([QuickOrderBridgePass::QUICK_ORDER_PROVIDER, null, 0], $container->getDefinition(QuickOrderBridgePass::SERVICE_ID)->getDecoratedService());
    }

    public function testDoesNothingWithoutQuickOrder(): void
    {
        $container = new ContainerBuilder();

        (new QuickOrderBridgePass())->process($container);

        self::assertFalse($container->hasDefinition(QuickOrderBridgePass::SERVICE_ID));
    }
}
