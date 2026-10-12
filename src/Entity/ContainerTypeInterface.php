<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;
use Sylius\Resource\Model\TranslatableInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * A pallet, truck, sea container or carton. Dimensions in millimetres, payload in grams.
 */
interface ContainerTypeInterface extends ResourceInterface, CodeAwareInterface, TimestampableInterface, ToggleableInterface, TranslatableInterface
{
    public const KIND_PALLET = 'pallet';

    public const KIND_TRUCK = 'truck';

    public const KIND_SEA_CONTAINER = 'sea_container';

    public const KIND_CARTON = 'carton';

    public const KINDS = [self::KIND_PALLET, self::KIND_TRUCK, self::KIND_SEA_CONTAINER, self::KIND_CARTON];

    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getKind(): string;

    public function setKind(string $kind): void;

    public function getInnerLength(): ?int;

    public function setInnerLength(?int $innerLength): void;

    public function getInnerWidth(): ?int;

    public function setInnerWidth(?int $innerWidth): void;

    public function getInnerHeight(): ?int;

    public function setInnerHeight(?int $innerHeight): void;

    /** Maximum height of the load (pallet networks: 1.8 to 2.2 m including the pallet), null: inner height. */
    public function getMaxLoadHeight(): ?int;

    public function setMaxLoadHeight(?int $maxLoadHeight): void;

    /** Maximum payload in grams (mandatory: a load can be full by weight long before every place is used). */
    public function getMaxPayload(): int;

    public function setMaxPayload(int $maxPayload): void;

    /** Number of places (e.g. 33 EUR pallet places in a standard semi-trailer), 1 for a pallet. */
    public function getSlots(): int;

    public function setSlots(int $slots): void;

    /** Type of the units occupying the places (e.g. EUR pallet in a truck): capacities are derived from it. */
    public function getNestedType(): ?self;

    public function setNestedType(?self $nestedType): void;

    /** Share of the inner volume really usable, in percent (volume indicator only). */
    public function getVolumeEfficiency(): int;

    public function setVolumeEfficiency(int $volumeEfficiency): void;

    /** Layer mode (1.1): plan by layers, limited by the load height and/or the maximum number of layers. */
    public function isLayerMode(): bool;

    public function setLayerMode(bool $layerMode): void;

    public function getMaxLayers(): ?int;

    public function setMaxLayers(?int $maxLayers): void;

    /** Preset figures to check with the carrier: shown as "indicative". */
    public function isIndicative(): bool;

    public function setIndicative(bool $indicative): void;

    public function getPosition(): int;

    public function setPosition(int $position): void;

    /** Usable volume in mm³ (inner length x width x load height x efficiency), null when a dimension is missing. */
    public function getUsableVolume(): ?float;

    /** @return ContainerTypeTranslationInterface */
    public function getTranslation(?string $locale = null): TranslationInterface;
}
