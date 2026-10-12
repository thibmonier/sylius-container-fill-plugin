<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/** Load plan of the lines for one container type. */
final class FillResult
{
    /**
     * @param list<ContainerFill> $containers
     * @param list<PlanLine> $lines
     * @param array<string, list<string>> $incomplete missing data per line key
     */
    public function __construct(
        public readonly ContainerSpec $container,
        public readonly array $containers,
        public readonly array $lines,
        public readonly bool $volumeAvailable,
        public readonly array $incomplete = [],
    ) {
    }

    public function isComplete(): bool
    {
        return [] === $this->incomplete;
    }

    public function containerCount(): int
    {
        return \count($this->containers);
    }

    /** The least filled container (the last one on ties), null for an empty plan. */
    public function leastFilled(): ?ContainerFill
    {
        $least = null;
        foreach ($this->containers as $container) {
            if (null === $least || $container->ratio() <= $least->ratio() + 1e-9) {
                $least = $container;
            }
        }

        return $least;
    }

    public function line(string $key): ?PlanLine
    {
        foreach ($this->lines as $line) {
            if ($line->key === $key) {
                return $line;
            }
        }

        return null;
    }

    public function totalUnits(): int
    {
        return array_sum(array_map(static fn (PlanLine $line): int => $line->quantity, $this->lines));
    }
}
