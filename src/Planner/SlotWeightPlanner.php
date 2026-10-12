<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/**
 * Default planner: capacity units ("40 cases fill one EUR pallet") and a mandatory payload cap.
 * The volume is computed as an indicator when every line and the container have dimensions; it
 * never splits, fills or blocks a container (memo section 2: core dimensions are often rough).
 *
 * Lines are split into containers by first-fit decreasing on whole cases (a partial last case
 * is one smaller item). "Own pallet" lines never share a container (single-place containers)
 * or take whole places (multi-place containers such as a truck of 33 pallet places).
 */
final class SlotWeightPlanner implements LoadPlannerInterface
{
    private const EPSILON = 1e-9;

    public function plan(ContainerSpec $container, array $lines): FillResult
    {
        $lines = array_values(array_filter($lines, static fn (PlanLine $line): bool => $line->quantity > 0));

        $incomplete = [];
        foreach ($lines as $line) {
            $missing = $line->missingData();
            if ([] !== $missing) {
                $incomplete[$line->key] = $missing;
            }
        }
        if ([] !== $incomplete || [] === $lines) {
            return new FillResult($container, [], $lines, false, $incomplete);
        }

        $volumeAvailable = null !== $container->usableVolume && $container->usableVolume > 0.0;
        foreach ($lines as $line) {
            if (null === $line->unitVolume || $line->unitVolume <= 0.0) {
                $volumeAvailable = false;
            }
        }

        $bins = [];
        $placeItems = [];
        $mixed = [];
        foreach ($lines as $line) {
            if (!$line->ownPallet) {
                $mixed[] = $line;

                continue;
            }
            if (1 === $container->slots) {
                $this->packLine($bins, $line, $container, $volumeAvailable, true);

                continue;
            }
            // Whole places of a multi-place container (e.g. pallets of their own in a truck).
            $perPlace = $this->unitsPerPlace($line, $container);
            for ($remaining = $line->quantity; $remaining > 0; $remaining -= $perPlace) {
                $placeItems[] = [$line, min($perPlace, $remaining)];
            }
        }

        usort($placeItems, fn (array $a, array $b): int => $this->size($this->placeDims($b[0], $b[1], $container, $volumeAvailable)) <=> $this->size($this->placeDims($a[0], $a[1], $container, $volumeAvailable)));
        foreach ($placeItems as [$line, $units]) {
            $dims = $this->placeDims($line, $units, $container, $volumeAvailable);
            $placed = false;
            foreach ($bins as $index => $bin) {
                if (null === $bin['dedicated'] && $this->fitCount($bin, $dims) >= 1) {
                    $this->add($bins[$index], $line->key, $units, $dims, 1);
                    $placed = true;

                    break;
                }
            }
            if (!$placed) {
                $bins[] = $this->emptyBin(null);
                $this->add($bins[\count($bins) - 1], $line->key, $units, $dims, 1);
            }
        }

        usort($mixed, fn (PlanLine $a, PlanLine $b): int => $this->size($this->unitDims($b, $b->unitsPerCase, $container, $volumeAvailable)) <=> $this->size($this->unitDims($a, $a->unitsPerCase, $container, $volumeAvailable)));
        foreach ($mixed as $line) {
            $this->packLine($bins, $line, $container, $volumeAvailable, false);
        }

        $containers = [];
        foreach ($bins as $index => $bin) {
            $containers[] = new ContainerFill(
                $index + 1,
                $bin['slot'],
                $bin['weight'],
                $volumeAvailable ? $bin['volume'] : null,
                $bin['units'],
                $this->isFull($bin, $lines, $container, $volumeAvailable),
                $bin['dedicated'],
            );
        }

        return new FillResult($container, $containers, $lines, $volumeAvailable);
    }

    /**
     * @param list<array{slot: float, weight: float, volume: float, units: array<string, int>, dedicated: ?string}> $bins
     */
    private function packLine(array &$bins, PlanLine $line, ContainerSpec $container, bool $volumeAvailable, bool $dedicated): void
    {
        $groups = [[$line->unitsPerCase, intdiv($line->quantity, $line->unitsPerCase)], [$line->quantity % $line->unitsPerCase, 1]];
        $owner = $dedicated ? $line->key : null;
        foreach ($groups as [$units, $count]) {
            if (0 === $units || 0 === $count) {
                continue;
            }
            $dims = $this->unitDims($line, $units, $container, $volumeAvailable);
            $remaining = $count;
            foreach ($bins as $index => $bin) {
                if ($bin['dedicated'] !== $owner || 0 === $remaining) {
                    continue;
                }
                $put = min($this->fitCount($bin, $dims), $remaining);
                if ($put > 0) {
                    $this->add($bins[$index], $line->key, $units, $dims, $put);
                    $remaining -= $put;
                }
            }
            while ($remaining > 0) {
                $bin = $this->emptyBin($owner);
                // An item larger than an empty container still gets one (overloaded, reported).
                $put = min(max(1, $this->fitCount($bin, $dims)), $remaining);
                $this->add($bin, $line->key, $units, $dims, $put);
                $bins[] = $bin;
                $remaining -= $put;
            }
        }
    }

    private function unitsPerPlace(PlanLine $line, ContainerSpec $container): int
    {
        return max(1, (int) floor((int) $line->capacity / $container->slots + self::EPSILON));
    }

    /** @return array{float, float, float} share of a container taken by $units units: places, weight, volume */
    private function unitDims(PlanLine $line, int $units, ContainerSpec $container, bool $volumeAvailable): array
    {
        return [
            $units * $line->slotShare(),
            $units * (float) $line->unitWeight / $container->maxPayload,
            $volumeAvailable ? $units * (float) $line->unitVolume / (float) $container->usableVolume : 0.0,
        ];
    }

    /** @return array{float, float, float} one whole place holding $units units */
    private function placeDims(PlanLine $line, int $units, ContainerSpec $container, bool $volumeAvailable): array
    {
        $dims = $this->unitDims($line, $units, $container, $volumeAvailable);
        $dims[0] = 1 / $container->slots;

        return $dims;
    }

    /** @param array{float, float, float} $dims */
    private function size(array $dims): float
    {
        // The volume is informative only (memo section 2): it never splits nor fills a container.
        return max($dims[0], $dims[1]);
    }

    /**
     * @param array{slot: float, weight: float, volume: float, units: array<string, int>, dedicated: ?string} $bin
     * @param array{float, float, float} $dims
     */
    private function fitCount(array $bin, array $dims): int
    {
        $fit = \PHP_INT_MAX;
        // Places and payload only: the volume is reported, never binding.
        foreach ([$bin['slot'], $bin['weight']] as $dimension => $used) {
            if ($dims[$dimension] <= 0.0) {
                continue;
            }
            $fit = min($fit, (int) floor((1.0 - $used + self::EPSILON) / $dims[$dimension]));
        }

        return max(0, $fit);
    }

    /**
     * @param array{slot: float, weight: float, volume: float, units: array<string, int>, dedicated: ?string} $bin
     * @param array{float, float, float} $dims
     */
    private function add(array &$bin, string $key, int $units, array $dims, int $count): void
    {
        $bin['slot'] += $dims[0] * $count;
        $bin['weight'] += $dims[1] * $count;
        $bin['volume'] += $dims[2] * $count;
        $bin['units'][$key] = ($bin['units'][$key] ?? 0) + $units * $count;
    }

    /** @return array{slot: float, weight: float, volume: float, units: array<string, int>, dedicated: ?string} */
    private function emptyBin(?string $dedicated): array
    {
        return ['slot' => 0.0, 'weight' => 0.0, 'volume' => 0.0, 'units' => [], 'dedicated' => $dedicated];
    }

    /**
     * Full: 100 % reached, or not even one more case (one more place for "own pallet" lines) of
     * the lines allowed in this container fits.
     *
     * @param array{slot: float, weight: float, volume: float, units: array<string, int>, dedicated: ?string} $bin
     * @param list<PlanLine> $lines
     */
    private function isFull(array $bin, array $lines, ContainerSpec $container, bool $volumeAvailable): bool
    {
        if (max($bin['slot'], $bin['weight']) >= 1.0 - 1e-6) {
            return true;
        }
        foreach ($lines as $line) {
            if (null !== $bin['dedicated']) {
                if ($line->key !== $bin['dedicated']) {
                    continue;
                }
                $dims = $this->unitDims($line, $line->unitsPerCase, $container, $volumeAvailable);
            } elseif ($line->ownPallet) {
                if (1 === $container->slots) {
                    continue;
                }
                $dims = $this->placeDims($line, $this->unitsPerPlace($line, $container), $container, $volumeAvailable);
            } else {
                $dims = $this->unitDims($line, $line->unitsPerCase, $container, $volumeAvailable);
            }
            if ($this->fitCount($bin, $dims) >= 1) {
                return false;
            }
        }

        return true;
    }
}
