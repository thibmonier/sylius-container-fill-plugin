<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Shipping;

use Sylius\Bundle\ShippingBundle\Attribute\AsShippingMethodRuleChecker;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;
use Tmonier\SyliusContainerFillPlugin\Form\Type\Shipping\ContainerTypeRuleConfigurationType;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/**
 * Shipping method available only when the load plan uses one of the configured container
 * types. Shipping methods are listed outside the order processing chain: the plan is computed
 * on demand.
 */
#[AsShippingMethodRuleChecker(type: self::TYPE, label: 'tmonier_container_fill.shipping.container_type_rule', formType: ContainerTypeRuleConfigurationType::class)]
final class ContainerTypeRuleChecker implements RuleCheckerInterface
{
    public const TYPE = 'tmonier_container_type';

    public function __construct(private readonly CartFillCheckerInterface $checker)
    {
    }

    public function isEligible(ShippingSubjectInterface $subject, array $configuration): bool
    {
        $order = $subject instanceof ShipmentInterface ? $subject->getOrder() : null;
        $codes = $configuration['container_types'] ?? [];
        if (!$order instanceof OrderInterface || !\is_array($codes) || [] === $codes) {
            return false;
        }

        $outcomes = $this->checker->check($order)->outcomes;
        if ([] === $outcomes) {
            return false;
        }
        foreach ($outcomes as $outcome) {
            if (!\in_array($outcome->result->container->code, $codes, true)) {
                return false;
            }
        }

        return true;
    }
}
