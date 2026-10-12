<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Twig;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogistics;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\ContainerSpec;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\OrderLoadPlanSnapshotRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillReport;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ContainerFillExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartFillCheckerInterface $checker,
        private readonly VariantLogisticsProviderInterface $logisticsProvider,
        private readonly ContainerTypeRepositoryInterface $containerTypeRepository,
        private readonly OrderLoadPlanSnapshotRepositoryInterface $snapshotRepository,
        private readonly bool $cartGauge,
        private readonly bool $productPreview,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_container_fill_report', $this->report(...)),
            new TwigFunction('tmonier_container_fill_snapshot', $this->snapshot(...)),
            new TwigFunction('tmonier_container_fill_logistics', $this->logistics(...)),
            new TwigFunction('tmonier_container_fill_preview', $this->preview(...)),
            new TwigFunction('tmonier_container_fill_cart_gauge_enabled', fn (): bool => $this->cartGauge),
            new TwigFunction('tmonier_container_fill_product_preview_enabled', fn (): bool => $this->productPreview),
        ];
    }

    public function report(mixed $order): CartFillReport
    {
        return $order instanceof OrderInterface ? $this->checker->check($order) : new CartFillReport();
    }

    public function snapshot(mixed $order): ?OrderLoadPlanSnapshotInterface
    {
        return $order instanceof OrderInterface ? $this->snapshotRepository->findOneByOrder($order) : null;
    }

    public function logistics(ProductVariantInterface $variant): VariantLogistics
    {
        return $this->logisticsProvider->getForVariant($variant);
    }

    /**
     * Indicative share of each enabled container type taken by $quantity units of the variant,
     * alone (the cart is the reference for mixed loads).
     *
     * @return list<array{layers: ?float, name: string, kind: string, capacity: int, percent: float, count: float, containers: int, indicative: bool, unitsPerLayer: ?int}> capacity: units that fit one container (places or payload); layers: layer mode only
     */
    public function preview(ProductVariantInterface $variant, mixed $quantity = 1): array
    {
        $quantity = is_numeric($quantity) ? max(1, (int) $quantity) : 1;
        $logistics = $this->logisticsProvider->getForVariant($variant);
        $rows = [];
        foreach ($this->containerTypeRepository->findEnabled() as $type) {
            $capacity = $logistics->unitsPerContainer($type);
            if (null === $capacity) {
                continue;
            }
            // Places or payload, whichever fills first: the units that fit one container.
            $unitWeight = $logistics->unitWeight();
            if (null !== $unitWeight && $unitWeight > 0) {
                $capacity = max(1, min($capacity, (int) floor($type->getMaxPayload() / $unitWeight + 1e-9)));
            }
            $share = $quantity / $capacity;
            // Layer mode: layers of the product and the stack height (or layer count) they take.
            $layers = null;
            $spec = ContainerSpec::fromContainerType($type);
            $perLayer = $logistics->unitsPerLayer($type);
            $layerHeight = $logistics->layerHeight();
            if ($spec->supportsLayers() && null !== $perLayer && $perLayer > 0 && null !== $layerHeight) {
                $layers = $quantity / $perLayer;
                $share = 0.0;
                if (null !== $spec->maxStackHeight && $spec->maxStackHeight > 0) {
                    $share = $layers * $layerHeight / $spec->maxStackHeight;
                }
                if (null !== $spec->maxLayers && $spec->maxLayers > 0) {
                    $share = max($share, $layers / $spec->maxLayers);
                }
                if (null !== $unitWeight && $unitWeight > 0) {
                    $share = max($share, $quantity * $unitWeight / max(1, $type->getMaxPayload()));
                }
            }
            $rows[] = [
                'layers' => null === $layers ? null : round($layers, $layers < 1 ? 2 : 1),
                'name' => (string) ($type->getName() ?? $type->getCode()),
                'kind' => $type->getKind(),
                'capacity' => $capacity,
                'percent' => round($share * 100, 1),
                'count' => round($share, 1),
                'containers' => (int) ceil($share - 1e-9),
                'indicative' => $type->isIndicative(),
                'unitsPerLayer' => $logistics->unitsPerLayer($type),
            ];
        }

        return $rows;
    }
}
