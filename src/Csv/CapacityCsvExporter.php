<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Csv;

use Tmonier\SyliusContainerFillPlugin\Logistics\EntityId;
use Tmonier\SyliusContainerFillPlugin\Repository\ProductVariantLogisticsRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\VariantContainerCapacityRepositoryInterface;

/** Writes the capacities in the import format (round trip). */
final class CapacityCsvExporter
{
    public function __construct(
        private readonly VariantContainerCapacityRepositoryInterface $capacityRepository,
        private readonly ProductVariantLogisticsRepositoryInterface $logisticsRepository,
    ) {
    }

    public function export(): string
    {
        $capacities = $this->capacityRepository->findAllForExport();
        $variantIds = array_values(array_unique(array_map(static fn ($capacity): int => EntityId::int($capacity->getVariant()?->getId()), $capacities)));
        $logistics = [] === $variantIds ? [] : $this->logisticsRepository->findByVariantIds($variantIds);

        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new \RuntimeException('Cannot open a temporary stream.');
        }
        fputcsv($stream, CapacityCsvFormat::COLUMNS, ';', '"', '');
        foreach ($capacities as $capacity) {
            $data = $logistics[EntityId::int($capacity->getVariant()?->getId())] ?? null;
            $weight = $data?->getCaseWeight();
            fputcsv($stream, [
                (string) $capacity->getVariant()?->getCode(),
                (string) $capacity->getContainerType()?->getCode(),
                (string) $capacity->getQuantity(),
                null === $capacity->getUnitsPerLayer() ? '' : (string) $capacity->getUnitsPerLayer(),
                null === $data ? '' : (string) $data->getUnitsPerCase(),
                null === $weight ? '' : rtrim(rtrim(number_format($weight / 1000, 3, '.', ''), '0'), '.'),
                null === $data?->getLayerHeight() ? '' : (string) $data->getLayerHeight(),
            ], ';', '"', '');
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
