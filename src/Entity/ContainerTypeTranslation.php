<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Resource\Model\AbstractTranslation;

class ContainerTypeTranslation extends AbstractTranslation implements ContainerTypeTranslationInterface, \Stringable
{
    protected ?int $id = null;

    protected ?string $name = null;

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
