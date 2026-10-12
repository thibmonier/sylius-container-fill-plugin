<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Model\ResourceInterface;

/** Load plan of a placed order, written when the checkout completes (rules may change later). */
interface OrderLoadPlanSnapshotInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getOrder(): ?OrderInterface;

    public function setOrder(?OrderInterface $order): void;

    /** @return array<string, mixed> */
    public function getPlan(): array;

    /** @param array<string, mixed> $plan */
    public function setPlan(array $plan): void;

    public function isSatisfied(): bool;

    public function setSatisfied(bool $satisfied): void;

    /** Admin who completed the order although the rules were not met. */
    public function getOverriddenBy(): ?string;

    public function setOverriddenBy(?string $overriddenBy): void;

    public function getCreatedAt(): \DateTimeInterface;
}
