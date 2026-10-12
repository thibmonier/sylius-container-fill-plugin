<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Entity;

use Sylius\Component\Core\Model\OrderInterface;

class OrderLoadPlanSnapshot implements OrderLoadPlanSnapshotInterface
{
    protected ?int $id = null;

    protected ?OrderInterface $order = null;

    /** @var array<string, mixed> */
    protected array $plan = [];

    protected bool $satisfied = true;

    protected ?string $overriddenBy = null;

    protected \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getPlan(): array
    {
        return $this->plan;
    }

    public function setPlan(array $plan): void
    {
        $this->plan = $plan;
    }

    public function isSatisfied(): bool
    {
        return $this->satisfied;
    }

    public function setSatisfied(bool $satisfied): void
    {
        $this->satisfied = $satisfied;
    }

    public function getOverriddenBy(): ?string
    {
        return $this->overriddenBy;
    }

    public function setOverriddenBy(?string $overriddenBy): void
    {
        $this->overriddenBy = $overriddenBy;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
