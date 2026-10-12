<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\DependencyInjection\Compiler\QuickOrderBridgePass;
use Tmonier\SyliusContainerFillPlugin\QuickOrder\CaseQuantityConstraintProvider;
use Tmonier\SyliusQuickOrderPlugin\Quantity\QuantityConstraint;

/** The test application installs tmonier/sylius-quick-order-plugin: its quantities follow the case multiples. */
final class QuickOrderBridgeTest extends KernelTestCase
{
    use ContainerFillTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testQuickOrderUsesTheCaseMultiples(): void
    {
        $provider = self::getContainer()->get(QuickOrderBridgePass::QUICK_ORDER_PROVIDER);
        self::assertInstanceOf(CaseQuantityConstraintProvider::class, $provider);
        $pallet = $this->catalog->pallet();
        $channel = $this->catalog->channel();

        $constraint = $provider->getConstraint($this->catalog->variant('WATER', [[$pallet, 480, null]], 9300, 6, false, true), $channel);
        self::assertInstanceOf(QuantityConstraint::class, $constraint);
        self::assertSame(6, $constraint->minimum);
        self::assertSame(6, $constraint->multipleOf);
        self::assertSame(18, $constraint->roundUp(14));

        // Case of 6 without the "multiple of" option, and no logistics data: the default provider answers.
        self::assertNull($provider->getConstraint($this->catalog->variant('LOOSE', [[$pallet, 480, null]], 9300, 6), $channel));
        self::assertNull($provider->getConstraint($this->catalog->plainVariant('PLAIN'), $channel));
    }
}
