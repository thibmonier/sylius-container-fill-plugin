<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * On Order and on the shop API CompleteOrder command, group "sylius_checkout_complete" only:
 * never in "sylius" (the cart form would refuse to save a partial cart).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class OrderContainerFillSatisfied extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return 'tmonier_container_fill_order_satisfied';
    }
}
