<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;

/**
 * Planner view of a container type: places, payload (grams), usable volume (mm³); layer mode
 * (1.1, opt-in): stack height available for goods (mm) and/or maximum number of layers.
 */
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
        public readonly bool $layerMode = false,
        public readonly ?int $maxStackHeight = null,
        public readonly ?int $maxLayers = null,
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
            $type->isLayerMode(),
            $type->getMaxLoadHeight() ?? $type->getInnerHeight(),
            $type->getMaxLayers(),
        );
    }

    /** Layer mode can be used: opted in, single place (a pallet, a carton), a height or a layer limit. */
    public function supportsLayers(): bool
    {
        return $this->layerMode && 1 === $this->slots && ((null !== $this->maxStackHeight && $this->maxStackHeight > 0) || (null !== $this->maxLayers && $this->maxLayers > 0));
    }
}
