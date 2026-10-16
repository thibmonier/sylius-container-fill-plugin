<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\Logistics;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductVariant;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerType;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogistics;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacity;
use Tmonier\SyliusContainerFillPlugin\Logistics\Units;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogistics;

final class VariantLogisticsTest extends TestCase
{
    public function testUnitsConvertSyliusFields(): void
    {
        $units = new Units('cm', 'kg');
        self::assertSame(120.0, $units->toMillimetres(12));
        self::assertSame(2500.0, $units->toGrams(2.5));
        self::assertEqualsWithDelta(453.59237, (new Units('in', 'lb'))->toGrams(1), 1e-9);

        $this->expectException(\InvalidArgumentException::class);
        new Units('furlong', 'kg');
    }

    public function testCapacityOfTheTypeOrDerivedFromItsPlaces(): void
    {
        [$pallet, $truck] = $this->types();
        $variant = $this->variant('WATER');
        $capacity = new VariantContainerCapacity();
        $capacity->setContainerType($pallet);
        $capacity->setQuantity(480);
        $capacity->setUnitsPerLayer(60);

        $logistics = new VariantLogistics($variant, null, ['EUR_PALLET' => $capacity], new Units());

        self::assertSame(480, $logistics->unitsPerContainer($pallet));
        self::assertSame(480 * 33, $logistics->unitsPerContainer($truck));
        self::assertSame(60, $logistics->unitsPerLayer($truck));
        self::assertNull((new VariantLogistics($variant, null, [], new Units()))->unitsPerContainer($truck));
    }

    public function testWeightFromTheCaseFirstThenFromTheVariant(): void
    {
        $variant = $this->variant('WATER');
        $variant->setWeight(1.5);
        $data = new ProductVariantLogistics();
        $data->setUnitsPerCase(6);

        self::assertSame(1500.0, (new VariantLogistics($variant, $data, [], new Units()))->unitWeight());

        $data->setCaseWeight(9300);
        self::assertSame(1550.0, (new VariantLogistics($variant, $data, [], new Units()))->unitWeight());

        $variant->setWeight(null);
        self::assertNull((new VariantLogistics($variant, null, [], new Units()))->unitWeight());
    }

    public function testVolumeNeedsEveryDimension(): void
    {
        $variant = $this->variant('BOX');
        $variant->setWidth(10);
        $variant->setHeight(20);
        self::assertNull((new VariantLogistics($variant, null, [], new Units()))->unitVolume());
        // Sylius returns 0 here (getShippingVolume): the raw fields are read instead.
        self::assertSame(0.0, (float) $variant->getShippingVolume());

        $variant->setDepth(30);
        self::assertSame(6000000.0, (new VariantLogistics($variant, null, [], new Units('cm')))->unitVolume());
    }

    public function testCaseMultipleNeedsCasesOfMoreThanOneUnit(): void
    {
        $data = new ProductVariantLogistics();
        $data->setCaseMultipleRequired(true);
        self::assertFalse((new VariantLogistics($this->variant('A'), $data, [], new Units()))->isCaseMultipleRequired());

        $data->setUnitsPerCase(12);
        $logistics = new VariantLogistics($this->variant('A'), $data, [], new Units());
        self::assertTrue($logistics->isCaseMultipleRequired());
        self::assertSame(12, $logistics->planLine($this->types()[0], 24)->unitsPerCase);
    }

    public function testALineWithoutUsableWeightIsIgnored(): void
    {
        $variant = $this->variant('PLAIN');
        self::assertTrue((new VariantLogistics($variant, null, [], new Units()))->isIgnored());

        foreach ([0.0, -2.5] as $weight) {
            $variant->setWeight($weight);
            self::assertTrue((new VariantLogistics($variant, null, [], new Units()))->isIgnored(), 'weight ' . $weight);
        }

        $variant->setWeight(1.0);
        self::assertFalse((new VariantLogistics($variant, null, [], new Units()))->isIgnored());
        self::assertFalse((new VariantLogistics($variant, null, [], new Units()))->isCorrupted());
    }

    public function testANonNumericWeightOrDimensionIsCorruptedAndIgnored(): void
    {
        foreach (['setWeight', 'setWidth', 'setHeight', 'setDepth'] as $setter) {
            foreach ([\NAN, \INF] as $value) {
                $variant = $this->variant('BAD');
                $variant->setWeight(1.0);
                $variant->setWidth(10);
                $variant->setHeight(10);
                $variant->setDepth(10);
                $variant->{$setter}($value);

                $logistics = new VariantLogistics($variant, null, [], new Units());
                self::assertTrue($logistics->isCorrupted(), $setter);
                self::assertTrue($logistics->isIgnored(), $setter);
                self::assertNull('setWeight' === $setter ? $logistics->unitWeight() : $logistics->unitVolume(), $setter);
            }
        }
    }

    /** @return array{ContainerType, ContainerType} */
    private function types(): array
    {
        $pallet = new ContainerType();
        $pallet->setCode('EUR_PALLET');
        $truck = new ContainerType();
        $truck->setCode('SEMI');
        $truck->setSlots(33);
        $truck->setNestedType($pallet);

        return [$pallet, $truck];
    }

    private function variant(string $code): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');

        return $variant;
    }
}
