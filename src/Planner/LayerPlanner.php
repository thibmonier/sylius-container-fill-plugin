<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/**
 * Layer mode (1.1, memo approach (d)): a pallet is a stack of layers, one product per layer
 * ("12 cases per layer, 180 mm high"). A container is limited by its stack height and/or its
 * number of layers, and by its payload. A partial layer takes the height of a full one, but
 * counts for its share in the fill, and a container with a partial layer that could be
 * completed is not full (full pallets are made of full layers).
 *
 * Lines are split into layers, stacked first-fit decreasing by layer weight (heavy layers at the
 * bottom). Non-stackable products bear nothing: at most one of their layers per container, on
 * top (the container is closed after it). "Own pallet" lines never share a container.
 */
final class LayerPlanner
{
    private const EPSILON = 1e-9;

    /** Layer mode applies: the container supports it and every line has layer data. */
    public function applies(ContainerSpec $container, array $lines): bool
    {
        if (!$container->supportsLayers() || [] === $lines) {
            return false;
        }
        foreach ($lines as $line) {
            if (!$line instanceof PlanLine || !$line->hasLayerData()) {
                return false;
            }
        }

        return true;
    }

    /** @param list<PlanLine> $lines lines with quantities, weights and layer data */
    public function plan(ContainerSpec $container, array $lines, bool $volumeAvailable): FillResult
    {
        $layers = [];
        foreach ($lines as $line) {
            $perLayer = (int) $line->unitsPerLayer;
            for ($remaining = $line->quantity; $remaining > 0; $remaining -= $perLayer) {
                $layers[] = ['line' => $line, 'units' => min($perLayer, $remaining)];
            }
        }
        // Stackable before non-stackable (on top), heavy layers first (at the bottom).
        usort($layers, static fn (array $a, array $b): int => [$b['line']->stackable, $b['units'] * (float) $b['line']->unitWeight] <=> [$a['line']->stackable, $a['units'] * (float) $a['line']->unitWeight]);

        /** @var list<array{height: int, filled: float, layerShare: float, weight: float, volume: float, layers: list<array{key: string, label: string, units: int, unitsPerLayer: int, height: int}>, units: array<string, int>, dedicated: ?string, closed: bool}> $bins */
        $bins = [];
        foreach ($layers as ['line' => $line, 'units' => $units]) {
            $owner = $line->ownPallet ? $line->key : null;
            $placed = false;
            foreach ($bins as $index => $bin) {
                if ($bin['dedicated'] === $owner && $this->fits($bin, $line, $units, $container)) {
                    $this->add($bins[$index], $line, $units, $container, $volumeAvailable);
                    $placed = true;

                    break;
                }
            }
            if (!$placed) {
                // A layer heavier or higher than an empty container still gets one (overloaded, reported).
                $bin = ['height' => 0, 'filled' => 0.0, 'layerShare' => 0.0, 'weight' => 0.0, 'volume' => 0.0, 'layers' => [], 'units' => [], 'dedicated' => $owner, 'closed' => false];
                $this->add($bin, $line, $units, $container, $volumeAvailable);
                $bins[] = $bin;
            }
        }

        $containers = [];
        foreach ($bins as $index => $bin) {
            $layerRatio = $this->layerRatio($bin['filled'], $bin['layerShare'], $container);
            $containers[] = new ContainerFill(
                $index + 1,
                0.0,
                $bin['weight'],
                $volumeAvailable ? $bin['volume'] : null,
                $bin['units'],
                $this->isFull($bin, $lines, $container, $layerRatio),
                $bin['dedicated'],
                $layerRatio,
                $bin['layers'],
                null === $container->maxStackHeight ? null : max(0, $container->maxStackHeight - $bin['height']),
            );
        }

        return new FillResult($container, $containers, $lines, $volumeAvailable, [], true);
    }

    /** @param array{height: int, filled: float, layerShare: float, weight: float, volume: float, layers: list<array{key: string, label: string, units: int, unitsPerLayer: int, height: int}>, units: array<string, int>, dedicated: ?string, closed: bool} $bin */
    private function fits(array $bin, PlanLine $line, int $units, ContainerSpec $container): bool
    {
        if ($bin['closed']) {
            return false;
        }
        if (null !== $container->maxStackHeight && $bin['height'] + (int) $line->layerHeight > $container->maxStackHeight) {
            return false;
        }
        if (null !== $container->maxLayers && \count($bin['layers']) + 1 > $container->maxLayers) {
            return false;
        }

        return $bin['weight'] + $units * (float) $line->unitWeight / $container->maxPayload <= 1.0 + self::EPSILON;
    }

    /** @param array{height: int, filled: float, layerShare: float, weight: float, volume: float, layers: list<array{key: string, label: string, units: int, unitsPerLayer: int, height: int}>, units: array<string, int>, dedicated: ?string, closed: bool} $bin */
    private function add(array &$bin, PlanLine $line, int $units, ContainerSpec $container, bool $volumeAvailable): void
    {
        $bin['height'] += (int) $line->layerHeight;
        $bin['filled'] += (int) $line->layerHeight * $units / (int) $line->unitsPerLayer;
        $bin['layerShare'] += $units / (int) $line->unitsPerLayer;
        $bin['weight'] += $units * (float) $line->unitWeight / $container->maxPayload;
        if ($volumeAvailable) {
            $bin['volume'] += $units * (float) $line->unitVolume / (float) $container->usableVolume;
        }
        $bin['layers'][] = ['key' => $line->key, 'label' => $line->label, 'units' => $units, 'unitsPerLayer' => (int) $line->unitsPerLayer, 'height' => (int) $line->layerHeight];
        $bin['units'][$line->key] = ($bin['units'][$line->key] ?? 0) + $units;
        // Nothing goes on a non-stackable product.
        $bin['closed'] = $bin['closed'] || !$line->stackable;
    }

    /** Fill by height and/or layer count, partial layers counted for their share. */
    private function layerRatio(float $filled, float $layers, ContainerSpec $container): float
    {
        $ratio = 0.0;
        if (null !== $container->maxStackHeight && $container->maxStackHeight > 0) {
            $ratio = $filled / $container->maxStackHeight;
        }
        if (null !== $container->maxLayers && $container->maxLayers > 0) {
            $ratio = max($ratio, $layers / $container->maxLayers);
        }

        return $ratio;
    }

    /**
     * Full: the stack height, the layer count or the payload is reached, the container is closed
     * by a non-stackable layer, or no further layer of the lines allowed in it fits.
     *
     * @param array{height: int, filled: float, layerShare: float, weight: float, volume: float, layers: list<array{key: string, label: string, units: int, unitsPerLayer: int, height: int}>, units: array<string, int>, dedicated: ?string, closed: bool} $bin
     * @param list<PlanLine> $lines
     */
    private function isFull(array $bin, array $lines, ContainerSpec $container, float $layerRatio): bool
    {
        if ($bin['weight'] >= 1.0 - 1e-6 || $layerRatio >= 1.0 - 1e-6) {
            return true;
        }
        // A partial layer that can be completed (payload permitting): not full yet.
        foreach ($bin['layers'] as $layer) {
            $line = $this->line($lines, $layer['key']);
            if ($layer['units'] < $layer['unitsPerLayer'] && null !== $line && $bin['weight'] + ($layer['unitsPerLayer'] - $layer['units']) * (float) $line->unitWeight / $container->maxPayload <= 1.0 + self::EPSILON) {
                return false;
            }
        }
        if ($bin['closed']) {
            return true;
        }
        foreach ($lines as $line) {
            if ($line->ownPallet ? $line->key !== $bin['dedicated'] : null !== $bin['dedicated']) {
                continue;
            }
            if ($this->fits($bin, $line, (int) $line->unitsPerLayer, $container)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<PlanLine> $lines */
    private function line(array $lines, string $key): ?PlanLine
    {
        foreach ($lines as $line) {
            if ($line->key === $key) {
                return $line;
            }
        }

        return null;
    }
}
