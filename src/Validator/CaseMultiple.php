<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Symfony\Component\Validator\Constraint;

/** Group "sylius": the quantity is a multiple of the case when the variant requires it (order item, add to cart and API cart commands). */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class CaseMultiple extends Constraint
{
    public string $message = 'tmonier_container_fill.case_multiple';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return 'tmonier_container_fill_case_multiple';
    }
}
