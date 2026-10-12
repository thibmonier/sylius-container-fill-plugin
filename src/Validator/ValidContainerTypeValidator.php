<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;

/** Places hold a single-place type (no nesting of nesting, no self reference). */
final class ValidContainerTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidContainerType) {
            throw new UnexpectedTypeException($constraint, ValidContainerType::class);
        }
        if (!$value instanceof ContainerTypeInterface) {
            return;
        }
        // Layer mode: one stack (a single place) limited by a height or a number of layers.
        if ($value->isLayerMode()) {
            if (1 !== $value->getSlots()) {
                $this->context->buildViolation('tmonier_container_fill.container_type.layer_mode.slots')->atPath('layerMode')->addViolation();
            }
            if (null === $value->getMaxLayers() && null === ($value->getMaxLoadHeight() ?? $value->getInnerHeight())) {
                $this->context->buildViolation('tmonier_container_fill.container_type.layer_mode.limit')->atPath('maxLayers')->addViolation();
            }
        }
        if (null === $value->getNestedType()) {
            return;
        }

        $nested = $value->getNestedType();
        if ($nested === $value || (null !== $value->getCode() && $nested->getCode() === $value->getCode()) || 1 !== $nested->getSlots()) {
            $this->context->buildViolation('tmonier_container_fill.container_type.nested_type.invalid')->atPath('nestedType')->addViolation();
        }
        if ($value->getSlots() < 2) {
            $this->context->buildViolation('tmonier_container_fill.container_type.nested_type.slots')->atPath('slots')->addViolation();
        }
    }
}
