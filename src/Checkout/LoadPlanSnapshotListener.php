<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Checkout;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\OrderLoadPlanSnapshotRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/**
 * Writes the load plan of the order when its checkout completes (shop, shop API, admin): later
 * rule or logistics changes do not alter placed orders. Persisted only, flushed with the order.
 */
final class LoadPlanSnapshotListener
{
    /** @param FactoryInterface<OrderLoadPlanSnapshotInterface> $snapshotFactory */
    public function __construct(
        private readonly CartFillCheckerInterface $checker,
        private readonly FillOverrideChecker $overrideChecker,
        private readonly FactoryInterface $snapshotFactory,
        private readonly OrderLoadPlanSnapshotRepositoryInterface $snapshotRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface || null !== $this->snapshotRepository->findOneByOrder($order)) {
            return;
        }

        $report = $this->checker->check($order);
        if (!$report->hasRules() && [] === $report->lineViolations) {
            return;
        }

        $snapshot = $this->snapshotFactory->createNew();
        $snapshot->setOrder($order);
        $snapshot->setPlan($report->toArray());
        $snapshot->setSatisfied($report->isSatisfied());
        if (!$report->isSatisfied()) {
            $administrator = $this->overrideChecker->overridingAdministrator();
            $snapshot->setOverriddenBy($administrator);
            $this->logger->notice('Order {order} completed although the container fill rules are not met (override by {administrator}).', [
                'order' => $order->getNumber() ?? $order->getId(),
                'administrator' => $administrator ?? 'nobody',
            ]);
        }

        $this->entityManager->persist($snapshot);
    }
}
