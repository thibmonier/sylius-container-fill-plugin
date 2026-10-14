<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Sylius\Bundle\ApiBundle\Context\UserContextInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusContainerFillPlugin\Api\Resource\OrderLoadPlan;
use Tmonier\SyliusContainerFillPlugin\Repository\OrderLoadPlanSnapshotRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/** @implements ProviderInterface<OrderLoadPlan> */
final readonly class OrderLoadPlanProvider implements ProviderInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CartFillCheckerInterface $checker,
        private OrderLoadPlanSnapshotRepositoryInterface $snapshotRepository,
        private UserContextInterface $userContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrderLoadPlan
    {
        $tokenValue = $uriVariables['tokenValue'] ?? null;
        $order = \is_string($tokenValue) ? $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]) : null;
        if (!$order instanceof OrderInterface || !$this->isAccessible($order)) {
            throw new NotFoundHttpException('Order not found.');
        }

        if (BaseOrderInterface::STATE_CART === $order->getState()) {
            $report = $this->checker->check($order);
            $data = $report->toArray();

            return new OrderLoadPlan($tokenValue, 'cart', (bool) $data['satisfied'], (array) $data['rules'], (array) $data['lineViolations'], plan: (array) $data['plan'], containerCount: $report->containerCount());
        }

        $snapshot = $this->snapshotRepository->findOneByOrder($order);
        if (!$snapshot instanceof \Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface) {
            return new OrderLoadPlan($tokenValue, 'none', true);
        }
        $plan = $snapshot->getPlan();

        return new OrderLoadPlan($tokenValue, 'snapshot', $snapshot->isSatisfied(), (array) ($plan['rules'] ?? []), (array) ($plan['lineViolations'] ?? []), $snapshot->getOverriddenBy(), plan: (array) ($plan['plan'] ?? []), containerCount: is_numeric($plan['containerCount'] ?? null) ? (int) $plan['containerCount'] : 0);
    }

    /**
     * Same rule as the core cart access (AddItemToCartHandler): an order of a registered
     * customer is only reachable by that customer; guest orders by their token.
     */
    private function isAccessible(OrderInterface $order): bool
    {
        if ($order->isCreatedByGuest()) {
            return true;
        }
        $customer = $order->getCustomer();
        if (!$customer instanceof CustomerInterface || !$customer->getUser() instanceof \Sylius\Component\User\Model\UserInterface) {
            return true;
        }
        $user = $this->userContext->getUser();

        return $user instanceof ShopUserInterface && $user->getCustomer()?->getId() === $customer->getId();
    }
}
