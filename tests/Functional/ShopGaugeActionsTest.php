<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Functional;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class ShopGaugeActionsTest extends WebTestCase
{
    use FunctionalTestTrait;
    use InteractsWithLiveComponents;

    public function testCompleteEmitsCartChangedOutsideTheCart(): void
    {
        [$cart, $variant, $component] = $this->gauge('embedded', 50);

        $component->call('complete', ['rule' => 0]);

        $this->assertComponentEmitEvent($component, 'sylius:shop:cart_changed')->withData(['cartId' => $cart->getId()]);
        self::assertSame(80, $this->quantity($cart));
        self::assertStringContainsString('The fill rules are met.', $component->render()->toString());
    }

    public function testRoundToLayerEmitsCartChanged(): void
    {
        [$cart, $variant, $component] = $this->gauge('embedded', 50);

        $component->call('roundToLayer', ['variant' => (string) $variant->getCode()]);

        $this->assertComponentEmitEvent($component, 'sylius:shop:cart_changed');
        self::assertSame(56, $this->quantity($cart));
    }

    public function testInsideTheCartTheActionReloadsThePage(): void
    {
        [$cart, , $component] = $this->gauge('cart', 50);

        $component->call('complete', ['rule' => 0]);

        $response = $component->response();
        self::assertTrue($response->isRedirection() || $response->headers->has('X-Live-Redirect'), (string) $response->getStatusCode());
        self::assertSame(80, $this->quantity($cart));
    }

    public function testUnknownSuggestionsChangeNothing(): void
    {
        [$cart, , $component] = $this->gauge('embedded', 50);

        $component->call('complete', ['rule' => 3]);
        $component->call('roundToLayer', ['variant' => 'UNKNOWN']);

        $this->assertComponentNotEmitEvent($component, 'sylius:shop:cart_changed');
        self::assertSame(50, $this->quantity($cart));
    }

    /** @return array{OrderInterface, \Sylius\Component\Core\Model\ProductVariantInterface, TestLiveComponent} */
    private function gauge(string $context, int $quantity): array
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $customer = $this->catalog->customer();
        $variant = $this->catalog->variant('A', [[$pallet, 40, 8]]);
        $cart = $this->catalog->cart([[$variant, $quantity]], $customer);
        $user = $customer->getUser();
        self::assertInstanceOf(ShopUserInterface::class, $user);

        $component = $this->createLiveComponent('tmonier_container_fill:gauge', ['context' => $context], $this->client)->actingAs($user, 'shop');

        return [$cart, $variant, $component];
    }

    private function quantity(OrderInterface $cart): int
    {
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(OrderInterface::class, $cart->getId());
        self::assertInstanceOf(OrderInterface::class, $reloaded);

        return $reloaded->getTotalQuantity();
    }
}
