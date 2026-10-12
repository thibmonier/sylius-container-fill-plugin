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
final class OrderLoadPlanProvider implements ProviderInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CartFillCheckerInterface $checker,
        private readonly OrderLoadPlanSnapshotRepositoryInterface $snapshotRepository,
        private readonly UserContextInterface $userContext,
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
            $data = $this->checker->check($order)->toArray();

            return new OrderLoadPlan($tokenValue, 'cart', (bool) $data['satisfied'], (array) $data['rules'], (array) $data['lineViolations']);
        }

        $snapshot = $this->snapshotRepository->findOneByOrder($order);
        if (null === $snapshot) {
            return new OrderLoadPlan($tokenValue, 'none', true);
        }
        $plan = $snapshot->getPlan();

        return new OrderLoadPlan($tokenValue, 'snapshot', $snapshot->isSatisfied(), (array) ($plan['rules'] ?? []), (array) ($plan['lineViolations'] ?? []), $snapshot->getOverriddenBy());
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
        if (!$customer instanceof CustomerInterface || null === $customer->getUser()) {
            return true;
        }
        $user = $this->userContext->getUser();

        return $user instanceof ShopUserInterface && $user->getCustomer()?->getId() === $customer->getId();
    }
}
