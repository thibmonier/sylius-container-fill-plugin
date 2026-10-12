<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Checkout;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/**
 * Sends the customer back to the cart when a checkout page is requested while the fill rules
 * are not met. Priority 4: after the router (32) and the firewall (8, the signed-in customer's
 * cart is known), before the Sylius CheckoutResolver (0); works with the resolver disabled.
 * No guard on the "address" transition: the cart state redirects to the address step (loop).
 */
final class CheckoutEntrySubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 4;

    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly CartFillCheckerInterface $checker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $routePrefix = 'sylius_shop_checkout_',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', self::PRIORITY]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (!\is_string($route) || !str_starts_with($route, $this->routePrefix)) {
            return;
        }

        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return;
        }
        if (!$cart instanceof OrderInterface || $cart->isEmpty() || $this->checker->check($cart)->isSatisfied()) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'tmonier_container_fill.checkout_blocked');
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary')));
    }
}
