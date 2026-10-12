<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;

class ContainerRule implements ContainerRuleInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $name = null;

    protected ?ChannelInterface $channel = null;

    protected string $scope = self::SCOPE_ALL;

    protected ?TaxonInterface $taxon = null;

    protected ?ProductInterface $product = null;

    protected ?CustomerGroupInterface $customerGroup = null;

    /** @var Collection<int, ContainerTypeInterface> */
    protected Collection $containerTypes;

    protected string $mode = self::MODE_FULL_ONLY;

    protected ?int $threshold = null;

    public function __construct()
    {
        $this->containerTypes = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    public function getTaxon(): ?TaxonInterface
    {
        return $this->taxon;
    }

    public function setTaxon(?TaxonInterface $taxon): void
    {
        $this->taxon = $taxon;
    }

    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    public function setProduct(?ProductInterface $product): void
    {
        $this->product = $product;
    }

    public function getCustomerGroup(): ?CustomerGroupInterface
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(?CustomerGroupInterface $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getContainerTypes(): Collection
    {
        return $this->containerTypes;
    }

    public function addContainerType(ContainerTypeInterface $containerType): void
    {
        if (!$this->hasContainerType($containerType)) {
            $this->containerTypes->add($containerType);
        }
    }

    public function removeContainerType(ContainerTypeInterface $containerType): void
    {
        $this->containerTypes->removeElement($containerType);
    }

    public function hasContainerType(ContainerTypeInterface $containerType): bool
    {
        return $this->containerTypes->contains($containerType);
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    public function getThreshold(): ?int
    {
        return $this->threshold;
    }

    public function setThreshold(?int $threshold): void
    {
        $this->threshold = $threshold;
    }
}
