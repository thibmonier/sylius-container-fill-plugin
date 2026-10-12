<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Cart;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/** Changes the quantity of a cart line like the cart form does (cart change events, processing, flush). */
final class CartQuantityAdjuster
{
    public function __construct(
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ObjectManager $manager,
        private readonly int $quantityLimit,
    ) {
    }

    /** False when the cart has no line of the variant or the quantity is out of range. */
    public function setQuantity(OrderInterface $cart, string $variantKey, int $quantity): bool
    {
        if (null === $cart->getId() || $quantity < 1 || $quantity > $this->quantityLimit) {
            return false;
        }

        $line = null;
        foreach ($cart->getItems() as $item) {
            if ($item instanceof OrderItemInterface && null !== $item->getVariant() &&
                ($item->getVariant()->getCode() ?? ('#' . (string) $item->getVariant()->getId())) === $variantKey
            ) {
                $line = $item;

                break;
            }
        }
        if (null === $line) {
            return false;
        }

        // Several lines of one variant (rare): the others keep their quantity.
        $others = 0;
        foreach ($cart->getItems() as $item) {
            if ($item !== $line && $item instanceof OrderItemInterface && $item->getVariant() === $line->getVariant()) {
                $others += $item->getQuantity();
            }
        }
        if ($quantity - $others < 1) {
            return false;
        }

        $this->quantityModifier->modify($line, $quantity - $others);
        $this->eventDispatcher->dispatch(new GenericEvent($cart), SyliusCartEvents::CART_CHANGE);
        $this->manager->flush();
        $this->eventDispatcher->dispatch(new GenericEvent($cart), SyliusCartEvents::CART_POST_CHANGE);

        return true;
    }
}
