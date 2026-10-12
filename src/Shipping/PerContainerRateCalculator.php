<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Shipping;

use Sylius\Bundle\CoreBundle\Form\Type\Shipping\Calculator\ChannelBasedFlatRateConfigurationType;
use Sylius\Bundle\ShippingBundle\Attribute\AsShippingCalculator;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface as BaseShipmentInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/**
 * Amount per container of the load plan (per channel), times the number of containers of the
 * applicable rules (at least one). One Sylius shipment for the whole order; the amount is
 * indicative, freight is confirmed after the order (see README).
 */
#[AsShippingCalculator(calculator: self::TYPE, label: 'tmonier_container_fill.shipping.per_container', formType: ChannelBasedFlatRateConfigurationType::class)]
final class PerContainerRateCalculator implements CalculatorInterface
{
    public const TYPE = 'tmonier_per_container';

    public function __construct(private readonly CartFillCheckerInterface $checker)
    {
    }

    public function calculate(BaseShipmentInterface $subject, array $configuration): int
    {
        if (!$subject instanceof ShipmentInterface) {
            return 0;
        }
        $order = $subject->getOrder();
        $channelCode = $order instanceof OrderInterface ? $order->getChannel()?->getCode() : null;
        if (!$order instanceof OrderInterface || null === $channelCode) {
            return 0;
        }

        $channelConfiguration = $configuration[$channelCode] ?? null;
        if (!\is_array($channelConfiguration) || !isset($channelConfiguration['amount']) || !is_numeric($channelConfiguration['amount'])) {
            throw new MissingChannelConfigurationException(sprintf('Channel %s has no amount defined for shipping method %s', (string) $order->getChannel()->getName(), (string) $subject->getMethod()?->getName()));
        }

        return (int) $channelConfiguration['amount'] * self::containerCount($this->checker, $order);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public static function containerCount(CartFillCheckerInterface $checker, OrderInterface $order): int
    {
        // Load plan containers: each line counted once even when several rules apply to it.
        return max(1, $checker->check($order)->containerCount());
    }
}
