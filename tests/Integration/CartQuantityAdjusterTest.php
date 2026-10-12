<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Cart\CartQuantityAdjuster;

/** The gauge actions change the cart like the core cart (processing and post change events), on Sylius 2.2 and 2.3. */
final class CartQuantityAdjusterTest extends KernelTestCase
{
    use ContainerFillTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testTheQuantityIsChangedAndTheCartEventsAreDispatched(): void
    {
        $pallet = $this->catalog->pallet();
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]]);
        $cart = $this->catalog->cart([[$variant, 10]]);
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $events = [];
        $listener = static function (object $event, string $name) use (&$events): void {
            $events[] = $name;
        };
        $dispatcher->addListener(SyliusCartEvents::CART_CHANGE, $listener);
        $dispatcher->addListener(CartQuantityAdjuster::CART_POST_CHANGE, $listener);
        $adjuster = self::getContainer()->get('tmonier_container_fill.cart_quantity_adjuster');
        self::assertInstanceOf(CartQuantityAdjuster::class, $adjuster);

        try {
            self::assertTrue($adjuster->setQuantity($cart, (string) $variant->getCode(), 40));
            self::assertFalse($adjuster->setQuantity($cart, 'UNKNOWN', 40));
        } finally {
            $dispatcher->removeListener(SyliusCartEvents::CART_CHANGE, $listener);
            $dispatcher->removeListener(CartQuantityAdjuster::CART_POST_CHANGE, $listener);
        }

        self::assertSame([SyliusCartEvents::CART_CHANGE, CartQuantityAdjuster::CART_POST_CHANGE], $events);
        self::assertSame(40, $cart->getItems()->first() ? $cart->getItems()->first()->getQuantity() : null);
    }
}
