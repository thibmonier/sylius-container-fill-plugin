<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Csv;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ProductVariantLogisticsRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\VariantContainerCapacityRepositoryInterface;

/**
 * Imports capacities (and optionally case data) per variant and container type. Every line is
 * checked first; the lines are written in one flush only when none is rejected (rejected lines
 * are reported by line number).
 */
final class CapacityCsvImporter
{
    private const MAX_INT = 100000000;

    /** @var array<string, ProductVariantLogisticsInterface> created during the current import (not flushed yet), by variant code */
    private array $createdLogistics = [];

    /**
     * @param RepositoryInterface<ProductVariantInterface> $variantRepository
     * @param FactoryInterface<ProductVariantLogisticsInterface> $logisticsFactory
     * @param FactoryInterface<VariantContainerCapacityInterface> $capacityFactory
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RepositoryInterface $variantRepository,
        private readonly ContainerTypeRepositoryInterface $containerTypeRepository,
        private readonly ProductVariantLogisticsRepositoryInterface $logisticsRepository,
        private readonly VariantContainerCapacityRepositoryInterface $capacityRepository,
        private readonly FactoryInterface $logisticsFactory,
        private readonly FactoryInterface $capacityFactory,
    ) {
    }

    public function import(string $contents, bool $dryRun = false): CapacityImportResult
    {
        $contents = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents);
        $rows = explode("\n", $contents);
        $header = (string) array_shift($rows);
        if ('' === trim($header)) {
            return new CapacityImportResult(0, 0, [1 => 'The file is empty.'], $dryRun);
        }
        $delimiter = substr_count($header, ';') >= substr_count($header, ',') ? ';' : ',';
        $columns = array_map(static fn (?string $column): string => strtolower(trim((string) $column)), str_getcsv($header, $delimiter, '"', ''));
        $missing = array_diff(CapacityCsvFormat::REQUIRED, $columns);
        if ([] !== $missing) {
            return new CapacityImportResult(0, 0, [1 => sprintf('Missing columns: %s.', implode(', ', $missing))], $dryRun);
        }
        $unknown = array_diff(array_filter($columns, static fn (string $column): bool => '' !== $column), CapacityCsvFormat::COLUMNS);
        if ([] !== $unknown) {
            return new CapacityImportResult(0, 0, [1 => sprintf('Unknown columns: %s.', implode(', ', $unknown))], $dryRun);
        }

        $errors = [];
        $valid = [];
        $seen = [];
        foreach ($rows as $offset => $row) {
            $lineNumber = $offset + 2;
            if ('' === trim($row)) {
                continue;
            }
            $values = str_getcsv($row, $delimiter, '"', '');
            if (\count($values) > \count($columns)) {
                $errors[$lineNumber] = 'Too many values.';

                continue;
            }
            $line = [];
            foreach ($columns as $index => $column) {
                $line[$column] = trim((string) ($values[$index] ?? ''));
            }

            $error = $this->check($line);
            if (null !== $error) {
                $errors[$lineNumber] = $error;

                continue;
            }
            $key = $line['variant_code'] . '|' . $line['container_type'];
            if (isset($seen[$key])) {
                $errors[$lineNumber] = sprintf('Duplicate of line %d.', $seen[$key]);

                continue;
            }
            $seen[$key] = $lineNumber;
            $valid[] = $line;
        }

        // All or nothing: a rejected line leaves the stored data unchanged.
        $write = !$dryRun && [] === $errors;
        $this->createdLogistics = [];
        $imported = 0;
        $removed = 0;
        foreach ($valid as $line) {
            '' === $line['capacity'] ? ++$removed : ++$imported;
            if ($write) {
                $this->apply($line);
            }
        }
        if ($write) {
            $this->entityManager->flush();
        }

        ksort($errors);

        return new CapacityImportResult($imported, $removed, $errors, $dryRun, $write);
    }

    /** @param array<string, string> $line */
    private function check(array $line): ?string
    {
        if ('' === $line['variant_code'] || null === $this->variant($line['variant_code'])) {
            return sprintf('Unknown variant "%s".', $line['variant_code']);
        }
        if ('' === $line['container_type'] || null === $this->containerTypeRepository->findOneByCode($line['container_type'])) {
            return sprintf('Unknown container type "%s".', $line['container_type']);
        }
        foreach (['capacity', 'units_per_layer', 'units_per_case', 'layer_height_mm'] as $column) {
            $value = $line[$column] ?? '';
            if ('' !== $value && (1 !== preg_match('/^\d+$/', $value) || (int) $value < 1 || (int) $value > self::MAX_INT)) {
                return sprintf('"%s" must be a whole number from 1 to %d.', $column, self::MAX_INT);
            }
        }
        $weight = $line['case_weight_kg'] ?? '';
        if ('' !== $weight && (1 !== preg_match('/^\d+([.,]\d{1,3})?$/', $weight) || (float) str_replace(',', '.', $weight) <= 0)) {
            return '"case_weight_kg" must be a positive number of kilograms (3 decimals at most).';
        }

        return null;
    }

    /** @param array<string, string> $line */
    private function apply(array $line): void
    {
        $variant = $this->variant($line['variant_code']);
        $type = $this->containerTypeRepository->findOneByCode($line['container_type']);
        if (null === $variant || null === $type) {
            return;
        }

        $capacity = $this->capacityRepository->findOneByVariantAndType($variant, $type);
        if ('' === $line['capacity']) {
            if (null !== $capacity) {
                $this->entityManager->remove($capacity);
            }
        } else {
            if (null === $capacity) {
                $capacity = $this->capacityFactory->createNew();
                $capacity->setVariant($variant);
                $capacity->setContainerType($type);
                $this->entityManager->persist($capacity);
            }
            $capacity->setQuantity((int) $line['capacity']);
            if ('' !== ($line['units_per_layer'] ?? '')) {
                $capacity->setUnitsPerLayer((int) $line['units_per_layer']);
            }
        }

        $unitsPerCase = $line['units_per_case'] ?? '';
        $weight = $line['case_weight_kg'] ?? '';
        $layerHeight = $line['layer_height_mm'] ?? '';
        if ('' === $unitsPerCase && '' === $weight && '' === $layerHeight) {
            return;
        }
        // Several lines of one variant (one per container type, the export format) share the
        // logistics row created for the first one: the repository does not see it before the flush.
        $logistics = $this->createdLogistics[$line['variant_code']] ?? $this->logisticsRepository->findOneByVariant($variant);
        if (null === $logistics) {
            $logistics = $this->logisticsFactory->createNew();
            $logistics->setVariant($variant);
            $this->entityManager->persist($logistics);
            $this->createdLogistics[$line['variant_code']] = $logistics;
        }
        if ('' !== $unitsPerCase) {
            $logistics->setUnitsPerCase((int) $unitsPerCase);
        }
        if ('' !== $weight) {
            $logistics->setCaseWeight((int) round((float) str_replace(',', '.', $weight) * 1000));
        }
        if ('' !== $layerHeight) {
            $logistics->setLayerHeight((int) $layerHeight);
        }
    }

    private function variant(string $code): ?ProductVariantInterface
    {
        $variant = $this->variantRepository->findOneBy(['code' => $code]);

        return $variant instanceof ProductVariantInterface ? $variant : null;
    }
}
