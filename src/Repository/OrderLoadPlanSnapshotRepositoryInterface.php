<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;

/** @extends RepositoryInterface<OrderLoadPlanSnapshotInterface> */
interface OrderLoadPlanSnapshotRepositoryInterface extends RepositoryInterface
{
    public function findOneByOrder(OrderInterface $order): ?OrderLoadPlanSnapshotInterface;
}
