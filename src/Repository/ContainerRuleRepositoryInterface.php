<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

/** @extends RepositoryInterface<ContainerRuleInterface> */
interface ContainerRuleRepositoryInterface extends RepositoryInterface
{
    /** @return list<ContainerRuleInterface> enabled rules of the channel with their container types */
    public function findEnabledByChannel(ChannelInterface $channel): array;
}
