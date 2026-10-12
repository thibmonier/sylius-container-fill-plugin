<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Order\Model\OrderInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;

/** @extends EntityRepository<OrderLoadPlanSnapshotInterface> */
class OrderLoadPlanSnapshotRepository extends EntityRepository implements OrderLoadPlanSnapshotRepositoryInterface
{
    public function findOneByOrder(OrderInterface $order): ?OrderLoadPlanSnapshotInterface
    {
        if (null === $order->getId()) {
            return null;
        }
        $snapshot = $this->findOneBy(['order' => $order]);

        return $snapshot instanceof OrderLoadPlanSnapshotInterface ? $snapshot : null;
    }
}
