<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

/** The target matches the scope; the threshold matches the mode. */
final class ValidContainerRuleValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidContainerRule) {
            throw new UnexpectedTypeException($constraint, ValidContainerRule::class);
        }
        if (!$value instanceof ContainerRuleInterface) {
            return;
        }

        if (!\in_array($value->getScope(), ContainerRuleInterface::SCOPES, true)) {
            $this->violation('scope.invalid', 'scope');
        }
        if (ContainerRuleInterface::SCOPE_TAXON === $value->getScope() && null === $value->getTaxon()) {
            $this->violation('taxon.not_null', 'taxon');
        }
        if (ContainerRuleInterface::SCOPE_PRODUCT === $value->getScope() && null === $value->getProduct()) {
            $this->violation('product.not_null', 'product');
        }

        $threshold = $value->getThreshold();
        switch ($value->getMode()) {
            case ContainerRuleInterface::MODE_MIN_FILL_PERCENT:
                if (null === $threshold || $threshold < 1 || $threshold > 100) {
                    $this->violation('threshold.percent', 'threshold');
                }

                break;
            case ContainerRuleInterface::MODE_MAX_CONTAINERS:
                if (null === $threshold || $threshold < 1) {
                    $this->violation('threshold.containers', 'threshold');
                }

                break;
            case ContainerRuleInterface::MODE_MULTIPLE_OF:
                if (null !== $threshold && $threshold < 1) {
                    $this->violation('threshold.multiple', 'threshold');
                }

                break;
            case ContainerRuleInterface::MODE_FULL_ONLY:
                break;
            default:
                $this->violation('mode.invalid', 'mode');
        }
    }

    private function violation(string $key, string $path): void
    {
        $this->context->buildViolation('tmonier_container_fill.container_rule.' . $key)->atPath($path)->addViolation();
    }
}
