<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvExporter;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvImporter;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;

final class CapacityCsvTest extends KernelTestCase
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

    public function testImportCreatesUpdatesAndRemovesCapacities(): void
    {
        $pallet = $this->catalog->pallet();
        $truck = $this->catalog->truck($pallet);
        $a = $this->catalog->plainVariant('A');
        $b = $this->catalog->variant('B', [[$pallet, 10, null], [$truck, 300, null]]);
        $csv = "\xEF\xBB\xBFvariant_code,container_type,capacity,units_per_layer,units_per_case,case_weight_kg\r\n" .
            sprintf("%s,%s,48,12,6,\"9,3\"\r\n", $a->getCode(), $pallet->getCode()) .
            sprintf("%s,%s,20,,,\r\n", $b->getCode(), $pallet->getCode()) .
            sprintf("%s,%s,,,,\r\n", $b->getCode(), $truck->getCode());

        $dryRun = $this->importer()->import($csv, true);
        self::assertSame([2, 1, [], false], [$dryRun->imported, $dryRun->removed, $dryRun->errors, $dryRun->written]);
        self::assertNull($this->capacity($a, $pallet));

        $result = $this->importer()->import($csv);
        self::assertTrue($result->written);
        $this->entityManager()->clear();
        $capacity = $this->capacity($a, $pallet);
        self::assertNotNull($capacity);
        self::assertSame([48, 12], [$capacity->getQuantity(), $capacity->getUnitsPerLayer()]);
        $logistics = $this->entityManager()->getRepository(ProductVariantLogisticsInterface::class)->findOneBy(['variant' => $a->getId()]);
        self::assertInstanceOf(ProductVariantLogisticsInterface::class, $logistics);
        self::assertSame([6, 9300], [$logistics->getUnitsPerCase(), $logistics->getCaseWeight()]);
        self::assertSame(20, $this->capacity($b, $pallet)?->getQuantity());
        self::assertNull($this->capacity($b, $truck));
    }

    public function testSeveralLinesOfAVariantWithoutLogisticsData(): void
    {
        $pallet = $this->catalog->pallet();
        $truck = $this->catalog->truck($pallet);
        $a = $this->catalog->plainVariant('A');
        $csv = "variant_code;container_type;capacity;units_per_layer;units_per_case;case_weight_kg\n" .
            sprintf("%s;%s;48;;6;9\n", $a->getCode(), $pallet->getCode()) .
            sprintf("%s;%s;1584;;6;9\n", $a->getCode(), $truck->getCode());

        $result = $this->importer()->import($csv);

        self::assertSame([], $result->errors);
        self::assertTrue($result->written);
        $this->entityManager()->clear();
        $logistics = $this->entityManager()->getRepository(ProductVariantLogisticsInterface::class)->findBy(['variant' => $a->getId()]);
        self::assertCount(1, $logistics);
        self::assertSame([6, 9000], [$logistics[0]->getUnitsPerCase(), $logistics[0]->getCaseWeight()]);
        self::assertSame(1584, $this->capacity($a, $truck)?->getQuantity());
    }

    public function testTheLayerHeightColumn(): void
    {
        $pallet = $this->catalog->layerPallet();
        $a = $this->catalog->plainVariant('A');
        $csv = "variant_code;container_type;capacity;units_per_layer;units_per_case;case_weight_kg;layer_height_mm\n" .
            sprintf("%s;%s;480;60;6;9.3;200\n", $a->getCode(), $pallet->getCode());

        self::assertSame([], $this->importer()->import($csv)->errors);
        $this->entityManager()->clear();
        $logistics = $this->entityManager()->getRepository(ProductVariantLogisticsInterface::class)->findOneBy(['variant' => $a->getId()]);
        self::assertInstanceOf(ProductVariantLogisticsInterface::class, $logistics);
        self::assertSame(200, $logistics->getLayerHeight());

        $exporter = self::getContainer()->get('tmonier_container_fill.csv.exporter');
        self::assertInstanceOf(CapacityCsvExporter::class, $exporter);
        self::assertStringContainsString(sprintf("%s;%s;480;60;6;9.3;200\n", $a->getCode(), $pallet->getCode()), $exporter->export());

        $bad = "variant_code;container_type;capacity;layer_height_mm\n" . sprintf("%s;%s;480;0\n", $a->getCode(), $pallet->getCode());
        self::assertStringContainsString('layer_height_mm', $this->importer()->import($bad)->errors[2] ?? '');
    }

    public function testTheImportIsAllOrNothing(): void
    {
        $pallet = $this->catalog->pallet();
        $a = $this->catalog->plainVariant('A');
        $csv = "variant_code;container_type;capacity\n" .
            sprintf("%s;%s;48\n", $a->getCode(), $pallet->getCode()) .
            sprintf("%s;%s;0\n", $a->getCode(), $pallet->getCode()) .
            sprintf("NOPE_%s;%s;5\n", $this->catalog->suffix, $pallet->getCode()) .
            sprintf("%s;NOPE;5\n", $a->getCode()) .
            sprintf("%s;%s;5;1\n", $a->getCode(), $pallet->getCode());

        $result = $this->importer()->import($csv);

        self::assertFalse($result->written);
        self::assertSame([3, 4, 5, 6], array_keys($result->errors));
        self::assertStringContainsString('whole number', $result->errors[3]);
        self::assertStringContainsString('Unknown variant', $result->errors[4]);
        self::assertStringContainsString('Unknown container type', $result->errors[5]);
        self::assertSame('Too many values.', $result->errors[6]);
        self::assertNull($this->capacity($a, $pallet));

        self::assertSame([1 => 'Missing columns: capacity.'], $this->importer()->import("variant_code;container_type\n")->errors);
        self::assertSame([1 => 'The file is empty.'], $this->importer()->import('')->errors);
    }

    public function testExportIsReimportable(): void
    {
        $pallet = $this->catalog->pallet();
        $a = $this->catalog->variant('A', [[$pallet, 40, 8]], 12500, 4);

        $exporter = self::getContainer()->get('tmonier_container_fill.csv.exporter');
        self::assertInstanceOf(CapacityCsvExporter::class, $exporter);
        $csv = $exporter->export();

        self::assertStringStartsWith("variant_code;container_type;capacity;units_per_layer;units_per_case;case_weight_kg;layer_height_mm\n", $csv);
        self::assertStringContainsString(sprintf("%s;%s;40;8;4;12.5;\n", $a->getCode(), $pallet->getCode()), $csv);
        $result = $this->importer()->import($csv, true);
        self::assertSame([], $result->errors);
    }

    public function testTheImportCommand(): void
    {
        $pallet = $this->catalog->pallet();
        $a = $this->catalog->plainVariant('A');
        $file = tempnam(sys_get_temp_dir(), 'tmonier_cf');
        self::assertIsString($file);
        $kernel = self::getContainer()->get('kernel');
        self::assertInstanceOf(\Symfony\Component\HttpKernel\KernelInterface::class, $kernel);
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('tmonier:container-fill:import-capacities'));

        try {
            file_put_contents($file, sprintf("variant_code;container_type;capacity\n%s;%s;oops\n", $a->getCode(), $pallet->getCode()));
            self::assertSame(1, $tester->execute(['file' => $file]));
            self::assertStringContainsString('nothing was written', $tester->getDisplay());

            file_put_contents($file, sprintf("variant_code;container_type;capacity\n%s;%s;24\n", $a->getCode(), $pallet->getCode()));
            self::assertSame(0, $tester->execute(['file' => $file]));
            self::assertStringContainsString('1 capacities imported', $tester->getDisplay());
        } finally {
            unlink($file);
        }
        self::assertSame(24, $this->capacity($a, $pallet)?->getQuantity());
    }

    private function capacity(object $variant, object $type): ?VariantContainerCapacityInterface
    {
        $capacity = $this->entityManager()->getRepository(VariantContainerCapacityInterface::class)->findOneBy(['variant' => $variant, 'containerType' => $type]);

        return $capacity instanceof VariantContainerCapacityInterface ? $capacity : null;
    }

    private function importer(): CapacityCsvImporter
    {
        $importer = self::getContainer()->get('tmonier_container_fill.csv.importer');
        self::assertInstanceOf(CapacityCsvImporter::class, $importer);

        return $importer;
    }
}
