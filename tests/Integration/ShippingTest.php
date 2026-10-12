<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Checker\Rule\RuleCheckerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillChecker;
use Tmonier\SyliusContainerFillPlugin\Shipping\ContainerTypeRuleChecker;
use Tmonier\SyliusContainerFillPlugin\Shipping\PerContainerRateCalculator;

final class ShippingTest extends KernelTestCase
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

    public function testThePerContainerRateIsMultipliedByTheNumberOfContainers(): void
    {
        $calculator = $this->calculator();
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 10);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]]);
        $configuration = [(string) $this->catalog->channel()->getCode() => ['amount' => 4500]];

        self::assertSame(9000, $calculator->calculate($this->shipment($this->catalog->cart([[$variant, 50]])), $configuration));
        self::assertSame(4500, $calculator->calculate($this->shipment($this->catalog->cart([[$variant, 40]])), $configuration));
        // Without an applicable rule: one container.
        self::assertSame(4500, $calculator->calculate($this->shipment($this->catalog->cart([[$this->catalog->plainVariant('B'), 3]])), $configuration));
    }

    public function testOverlappingRulesDoNotCountAContainerTwice(): void
    {
        $pallet = $this->catalog->pallet();
        $taxon = $this->catalog->taxon('PALLETS');
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 10);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_FULL_ONLY, null, $taxon);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], taxon: $taxon);

        $amount = $this->calculator()->calculate($this->shipment($this->catalog->cart([[$variant, 40]])), [(string) $this->catalog->channel()->getCode() => ['amount' => 4500]]);

        self::assertSame(4500, $amount);
    }

    public function testAChannelWithoutAmountIsAConfigurationError(): void
    {
        $this->expectException(\Sylius\Component\Core\Exception\MissingChannelConfigurationException::class);

        $this->calculator()->calculate($this->shipment($this->catalog->cart([[$this->catalog->plainVariant('A'), 1]])), ['OTHER' => ['amount' => 1]]);
    }

    public function testTheContainerTypeRule(): void
    {
        $checker = $this->ruleChecker();
        $pallet = $this->catalog->pallet();
        $truck = $this->catalog->truck($pallet);
        $this->catalog->rule([$pallet, $truck], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 10);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]]);

        $small = $this->shipment($this->catalog->cart([[$variant, 30]]));
        self::assertTrue($checker->isEligible($small, ['container_types' => [(string) $pallet->getCode()]]));
        self::assertFalse($checker->isEligible($small, ['container_types' => [(string) $truck->getCode()]]));
        self::assertFalse($checker->isEligible($small, ['container_types' => []]));

        // No plan (no applicable rule): not eligible.
        $this->catalog->disableOtherRules();
        $checkerService = $this->checker();
        self::assertInstanceOf(CartFillChecker::class, $checkerService);
        $checkerService->reset();
        $plain = $this->shipment($this->catalog->cart([[$variant, 30]]));
        self::assertFalse($checker->isEligible($plain, ['container_types' => [(string) $pallet->getCode()]]));
    }

    private function shipment(OrderInterface $order): ShipmentInterface
    {
        /** @var FactoryInterface<ShipmentInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.shipment');
        $shipment = $factory->createNew();
        $shipment->setOrder($order);

        return $shipment;
    }

    private function calculator(): CalculatorInterface
    {
        $registry = self::getContainer()->get('sylius.registry.shipping_calculator');
        self::assertInstanceOf(ServiceRegistryInterface::class, $registry);
        $calculator = $registry->get(PerContainerRateCalculator::TYPE);
        self::assertInstanceOf(PerContainerRateCalculator::class, $calculator);

        return $calculator;
    }

    private function ruleChecker(): RuleCheckerInterface
    {
        $registry = self::getContainer()->get('sylius.registry.shipping_method_rule_checker');
        self::assertInstanceOf(ServiceRegistryInterface::class, $registry);
        $checker = $registry->get(ContainerTypeRuleChecker::TYPE);
        self::assertInstanceOf(ContainerTypeRuleChecker::class, $checker);

        return $checker;
    }
}
