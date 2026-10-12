<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Fixture\ContainerFillDemoFixture;
use Tmonier\SyliusContainerFillPlugin\Fixture\ContainerTypePresets;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;

/** The fixtures are idempotent (the test database is loaded with them) and the demo is consistent. */
final class FixturesTest extends KernelTestCase
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

    public function testThePresetsAndTheDemo(): void
    {
        $this->fixture('tmonier_container_fill.fixture.container_types')->load([]);
        $this->fixture('tmonier_container_fill.fixture.demo')->load([]);
        $this->fixture('tmonier_container_fill.fixture.demo')->load([]);

        $types = $this->entityManager()->getRepository(ContainerTypeInterface::class);
        foreach (ContainerTypePresets::PRESETS as $preset) {
            self::assertCount(1, $types->findBy(['code' => $preset['code']]), $preset['code']);
        }
        $semi = $types->findOneBy(['code' => 'SEMI_13_6']);
        self::assertInstanceOf(ContainerTypeInterface::class, $semi);
        self::assertSame('EUR_PALLET', $semi->getNestedType()?->getCode());
        self::assertSame(33, $semi->getSlots());
        self::assertSame(24000000, $semi->getMaxPayload());

        $products = $this->entityManager()->getRepository(ProductInterface::class)->findBy(['code' => ['TMONIER_CF_WATER', 'TMONIER_CF_CEMENT', 'TMONIER_CF_TILES']]);
        self::assertCount(3, $products);
        $provider = self::getContainer()->get('tmonier_container_fill.logistics_provider');
        self::assertInstanceOf(VariantLogisticsProviderInterface::class, $provider);
        foreach ($products as $product) {
            $variant = $product->getVariants()->first();
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            self::assertGreaterThan(0, $provider->getForVariant($variant)->unitsPerContainer($semi), (string) $product->getCode());
            self::assertNotNull($provider->getForVariant($variant)->unitWeight(), (string) $product->getCode());
        }

        $rules = $this->entityManager()->getRepository(ContainerRuleInterface::class)->findBy(['name' => 'Pallet goods: full pallets or a full truck']);
        self::assertNotEmpty($rules);
        self::assertSame(ContainerFillDemoFixture::TAXON_CODE, $rules[0]->getTaxon()?->getCode());

        $buyer = $this->entityManager()->getRepository(ShopUserInterface::class)->findOneBy(['username' => ContainerFillDemoFixture::BUYER_EMAIL]);
        self::assertInstanceOf(ShopUserInterface::class, $buyer);
        self::assertNotNull($buyer->getVerifiedAt());
        self::assertTrue($buyer->isEnabled());
    }

    private function fixture(string $id): FixtureInterface
    {
        $fixture = self::getContainer()->get($id);
        self::assertInstanceOf(FixtureInterface::class, $fixture);

        return $fixture;
    }
}
