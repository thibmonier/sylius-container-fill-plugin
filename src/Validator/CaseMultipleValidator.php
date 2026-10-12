<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;

final class CaseMultipleValidator extends ConstraintValidator
{
    public function __construct(private readonly VariantLogisticsProviderInterface $logisticsProvider)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CaseMultiple) {
            throw new UnexpectedTypeException($constraint, CaseMultiple::class);
        }
        if (!$value instanceof OrderItemInterface || null === $value->getVariant()) {
            return;
        }
        $order = $value->getOrder();
        if (null !== $order && OrderInterface::STATE_CART !== $order->getState()) {
            return;
        }

        $logistics = $this->logisticsProvider->getForVariant($value->getVariant());
        if (!$logistics->isCaseMultipleRequired() || 0 === $value->getQuantity() % $logistics->unitsPerCase()) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->setParameter('%multiple%', (string) $logistics->unitsPerCase())
            ->atPath('quantity')
            ->addViolation()
        ;
    }
}
