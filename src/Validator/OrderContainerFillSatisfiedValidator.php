<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusContainerFillPlugin\Checkout\FillOverrideChecker;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

final class OrderContainerFillSatisfiedValidator extends ConstraintValidator
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly CartFillCheckerInterface $checker,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FillOverrideChecker $overrideChecker,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OrderContainerFillSatisfied) {
            throw new UnexpectedTypeException($constraint, OrderContainerFillSatisfied::class);
        }

        $order = $this->resolveOrder($value);
        // Placed orders keep the rules of their checkout (grandfathering).
        if (!$order instanceof \Sylius\Component\Core\Model\OrderInterface || BaseOrderInterface::STATE_CART !== $order->getState()) {
            return;
        }

        $report = $this->checker->check($order);
        if ($report->isSatisfied()) {
            return;
        }

        $administrator = $this->overrideChecker->overridingAdministrator();
        if (null !== $administrator) {
            $this->logger->notice('Container fill rules overridden by {administrator} for order {order}.', [
                'administrator' => $administrator,
                'order' => $order->getTokenValue() ?? $order->getId(),
            ]);

            return;
        }

        foreach ($report->violations() as $violation) {
            $this->context
                ->buildViolation('tmonier_container_fill.violation.' . $violation->code)
                ->setParameters(array_map(strval(...), $violation->translationParameters()))
                ->setCode($violation->code)
                ->addViolation()
            ;
        }
    }

    private function resolveOrder(mixed $value): ?OrderInterface
    {
        if ($value instanceof OrderInterface) {
            return $value;
        }
        // Sylius\Bundle\ApiBundle\Command\Checkout\CompleteOrder (the API bundle is optional).
        if (\is_object($value) && property_exists($value, 'orderTokenValue') && \is_string($value->orderTokenValue)) {
            $order = $this->orderRepository->findCartByTokenValue($value->orderTokenValue);

            return $order instanceof OrderInterface ? $order : null;
        }

        return null;
    }
}
