<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

/**
 * Fill rule of a channel: the cart lines in scope must fill the allowed containers as required
 * by the mode, otherwise the checkout is blocked.
 */
interface ContainerRuleInterface extends ResourceInterface, TimestampableInterface, ToggleableInterface
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_TAXON = 'taxon';

    public const SCOPE_PRODUCT = 'product';

    public const SCOPES = [self::SCOPE_ALL, self::SCOPE_TAXON, self::SCOPE_PRODUCT];

    /** Every container filled to at least the threshold (percent). */
    public const MODE_MIN_FILL_PERCENT = 'min_fill_percent';

    /** Every container full. */
    public const MODE_FULL_ONLY = 'full_only';

    /** Every line in scope ordered in multiples of the threshold (units), or of its case when empty. */
    public const MODE_MULTIPLE_OF = 'multiple_of';

    /** At most the threshold of containers. */
    public const MODE_MAX_CONTAINERS = 'max_containers';

    public const MODES = [self::MODE_MIN_FILL_PERCENT, self::MODE_FULL_ONLY, self::MODE_MULTIPLE_OF, self::MODE_MAX_CONTAINERS];

    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): void;

    public function getScope(): string;

    public function setScope(string $scope): void;

    public function getTaxon(): ?TaxonInterface;

    public function setTaxon(?TaxonInterface $taxon): void;

    public function getProduct(): ?ProductInterface;

    public function setProduct(?ProductInterface $product): void;

    /** Null: every customer (a rule of the customer's group replaces the rules without group of the same scope). */
    public function getCustomerGroup(): ?CustomerGroupInterface;

    public function setCustomerGroup(?CustomerGroupInterface $customerGroup): void;

    /** @return Collection<int, ContainerTypeInterface> */
    public function getContainerTypes(): Collection;

    public function addContainerType(ContainerTypeInterface $containerType): void;

    public function removeContainerType(ContainerTypeInterface $containerType): void;

    public function hasContainerType(ContainerTypeInterface $containerType): bool;

    public function getMode(): string;

    public function setMode(string $mode): void;

    public function getThreshold(): ?int;

    public function setThreshold(?int $threshold): void;
}
