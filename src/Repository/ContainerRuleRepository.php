<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

/** @extends EntityRepository<ContainerRuleInterface> */
class ContainerRuleRepository extends EntityRepository implements ContainerRuleRepositoryInterface
{
    public function findEnabledByChannel(ChannelInterface $channel): array
    {
        /** @var list<ContainerRuleInterface> $rules */
        $rules = $this->createQueryBuilder('o')
            ->addSelect('containerType')
            ->leftJoin('o.containerTypes', 'containerType')
            ->andWhere('o.channel = :channel')
            ->andWhere('o.enabled = true')
            ->setParameter('channel', $channel)
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rules;
    }
}
