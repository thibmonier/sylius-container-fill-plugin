<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Twig\Component;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Tmonier\SyliusContainerFillPlugin\Cart\CartQuantityAdjuster;
use Tmonier\SyliusContainerFillPlugin\Planner\FillResult;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillReport;

/**
 * Fill gauge of the current cart (always the cart of the cart context, never a cart id sent by
 * the browser). Refreshed on "sylius:shop:cart_changed".
 *
 * Its actions ("complete the container", "round up to the layer") change the cart and emit
 * "sylius:shop:cart_changed". Inside the cart page (context "cart") they reload the page
 * instead: the core cart form keeps the quantities in the browser and would save the old ones
 * back on its next change.
 */
final class ContainerGaugeComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use HookableLiveComponentTrait;

    public const CART_CHANGED = 'sylius:shop:cart_changed';

    public const CONTEXT_CART = 'cart';

    public const CONTEXT_EMBEDDED = 'embedded';

    #[LiveProp]
    public string $context = self::CONTEXT_CART;

    private ?CartFillReport $report = null;

    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly CartFillCheckerInterface $checker,
        private readonly CartQuantityAdjuster $adjuster,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCart(): ?OrderInterface
    {
        $cart = $this->cartContext->getCart();

        return $cart instanceof OrderInterface ? $cart : null;
    }

    public function getReport(): CartFillReport
    {
        if (!$this->report instanceof \Tmonier\SyliusContainerFillPlugin\Rule\CartFillReport) {
            $cart = $this->getCart();
            $this->report = $cart instanceof \Sylius\Component\Core\Model\OrderInterface ? $this->checker->check($cart) : new CartFillReport();
        }

        return $this->report;
    }

    /**
     * Lines that can be rounded up to the next full layer of the retained container type.
     *
     * @return list<array{key: string, label: string, quantity: int, target: int}>
     */
    public function getLayerRoundings(): array
    {
        $roundings = [];
        foreach ($this->getReport()->plan as $outcome) {
            foreach ($this->roundableLines($outcome->result) as $rounding) {
                $roundings[$rounding['key']] ??= $rounding;
            }
        }

        return array_values($roundings);
    }

    #[LiveListener(self::CART_CHANGED)]
    public function refresh(): void
    {
        $this->report = null;
    }

    #[LiveAction]
    public function complete(#[LiveArg] int $rule): ?RedirectResponse
    {
        $cart = $this->getCart();
        $suggestion = $this->getReport()->outcomes[$rule]->suggestion ?? null;
        if (!$cart instanceof \Sylius\Component\Core\Model\OrderInterface || null === $suggestion) {
            return null;
        }

        $quantity = (int) $this->getReport()->outcomes[$rule]->result->line($suggestion->lineKey)?->quantity + $suggestion->addUnits;

        return $this->changed($cart, $suggestion->lineKey, $quantity, 'tmonier_container_fill.completed', $suggestion->label, $suggestion->addUnits);
    }

    #[LiveAction]
    public function roundToLayer(#[LiveArg] string $variant): ?RedirectResponse
    {
        $cart = $this->getCart();
        if (!$cart instanceof \Sylius\Component\Core\Model\OrderInterface) {
            return null;
        }
        foreach ($this->getLayerRoundings() as $rounding) {
            if ($rounding['key'] === $variant) {
                return $this->changed($cart, $variant, $rounding['target'], 'tmonier_container_fill.rounded_to_layer', $rounding['label'], $rounding['target'] - $rounding['quantity']);
            }
        }

        return null;
    }

    private function changed(OrderInterface $cart, string $variant, int $quantity, string $flash, string $label, int $added): ?RedirectResponse
    {
        if (!$this->adjuster->setQuantity($cart, $variant, $quantity)) {
            return null;
        }
        $this->report = null;

        $session = $this->requestStack->getMainRequest()?->hasSession() ? $this->requestStack->getMainRequest()->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', ['message' => $flash, 'parameters' => ['%product%' => $label, '%quantity%' => $added]]);
        }

        if (self::CONTEXT_CART === $this->context) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary'));
        }

        $this->emit(self::CART_CHANGED, ['cartId' => $cart->getId()]);

        return null;
    }

    /** @return list<array{key: string, label: string, quantity: int, target: int}> */
    private function roundableLines(FillResult $result): array
    {
        $roundings = [];
        foreach ($result->lines as $line) {
            $layer = $line->unitsPerLayer;
            if (null === $layer || $layer < 2 || 0 === $line->quantity % $layer) {
                continue;
            }
            $roundings[] = [
                'key' => $line->key,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'target' => (intdiv($line->quantity, $layer) + 1) * $layer,
            ];
        }

        return $roundings;
    }
}
