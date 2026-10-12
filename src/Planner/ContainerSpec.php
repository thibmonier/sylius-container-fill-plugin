<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;

/** Planner view of a container type: places, payload (grams), usable volume (mm³). */
final class ContainerSpec
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $kind,
        public readonly int $slots,
        public readonly int $maxPayload,
        public readonly ?float $usableVolume = null,
        public readonly bool $indicative = false,
    ) {
        if ($slots < 1 || $maxPayload < 1) {
            throw new \InvalidArgumentException('A container needs at least one place and a payload.');
        }
    }

    public static function fromContainerType(ContainerTypeInterface $type): self
    {
        return new self(
            (string) $type->getCode(),
            (string) ($type->getName() ?? $type->getCode()),
            $type->getKind(),
            max(1, $type->getSlots()),
            max(1, $type->getMaxPayload()),
            $type->getUsableVolume(),
            $type->isIndicative(),
        );
    }
}
