<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Twig;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogistics;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;
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
     * @return list<array{name: string, kind: string, capacity: int, percent: float, containers: int, indicative: bool, unitsPerLayer: ?int}>
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
            // Places or payload, whichever fills first.
            $share = $quantity / $capacity;
            $unitWeight = $logistics->unitWeight();
            if (null !== $unitWeight) {
                $share = max($share, $quantity * $unitWeight / max(1, $type->getMaxPayload()));
            }
            $rows[] = [
                'name' => (string) ($type->getName() ?? $type->getCode()),
                'kind' => $type->getKind(),
                'capacity' => $capacity,
                'percent' => round($share * 100, 1),
                'containers' => (int) ceil($share - 1e-9),
                'indicative' => $type->isIndicative(),
                'unitsPerLayer' => $logistics->unitsPerLayer($type),
            ];
        }

        return $rows;
    }
}
