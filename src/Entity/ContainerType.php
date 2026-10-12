<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;
use Sylius\Resource\Model\TranslatableTrait;
use Sylius\Resource\Model\TranslationInterface;

class ContainerType implements ContainerTypeInterface, \Stringable
{
    use TimestampableTrait;
    use ToggleableTrait;
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    protected ?int $id = null;

    protected ?string $code = null;

    protected string $kind = self::KIND_PALLET;

    protected ?int $innerLength = null;

    protected ?int $innerWidth = null;

    protected ?int $innerHeight = null;

    protected ?int $maxLoadHeight = null;

    protected int $maxPayload = 1000000;

    protected int $slots = 1;

    protected ?ContainerTypeInterface $nestedType = null;

    protected int $volumeEfficiency = 85;

    protected bool $indicative = false;

    protected int $position = 0;

    public function __construct()
    {
        $this->initializeTranslationsCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        return (string) ($this->getName() ?? $this->code);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->getTranslation()->getName();
    }

    public function setName(?string $name): void
    {
        $this->getTranslation()->setName($name);
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }

    public function getInnerLength(): ?int
    {
        return $this->innerLength;
    }

    public function setInnerLength(?int $innerLength): void
    {
        $this->innerLength = $innerLength;
    }

    public function getInnerWidth(): ?int
    {
        return $this->innerWidth;
    }

    public function setInnerWidth(?int $innerWidth): void
    {
        $this->innerWidth = $innerWidth;
    }

    public function getInnerHeight(): ?int
    {
        return $this->innerHeight;
    }

    public function setInnerHeight(?int $innerHeight): void
    {
        $this->innerHeight = $innerHeight;
    }

    public function getMaxLoadHeight(): ?int
    {
        return $this->maxLoadHeight;
    }

    public function setMaxLoadHeight(?int $maxLoadHeight): void
    {
        $this->maxLoadHeight = $maxLoadHeight;
    }

    public function getMaxPayload(): int
    {
        return $this->maxPayload;
    }

    public function setMaxPayload(int $maxPayload): void
    {
        $this->maxPayload = $maxPayload;
    }

    public function getSlots(): int
    {
        return $this->slots;
    }

    public function setSlots(int $slots): void
    {
        $this->slots = $slots;
    }

    public function getNestedType(): ?ContainerTypeInterface
    {
        return $this->nestedType;
    }

    public function setNestedType(?ContainerTypeInterface $nestedType): void
    {
        $this->nestedType = $nestedType;
    }

    public function getVolumeEfficiency(): int
    {
        return $this->volumeEfficiency;
    }

    public function setVolumeEfficiency(int $volumeEfficiency): void
    {
        $this->volumeEfficiency = $volumeEfficiency;
    }

    public function isIndicative(): bool
    {
        return $this->indicative;
    }

    public function setIndicative(bool $indicative): void
    {
        $this->indicative = $indicative;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getUsableVolume(): ?float
    {
        $height = $this->maxLoadHeight ?? $this->innerHeight;
        if (null === $this->innerLength || null === $this->innerWidth || null === $height ||
            $this->innerLength <= 0 || $this->innerWidth <= 0 || $height <= 0
        ) {
            return null;
        }

        return (float) $this->innerLength * $this->innerWidth * $height * $this->volumeEfficiency / 100;
    }

    /** @return ContainerTypeTranslationInterface */
    public function getTranslation(?string $locale = null): TranslationInterface
    {
        /** @var ContainerTypeTranslationInterface $translation */
        $translation = $this->doGetTranslation($locale);

        return $translation;
    }

    protected function createTranslation(): ContainerTypeTranslationInterface
    {
        return new ContainerTypeTranslation();
    }
}
