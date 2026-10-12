<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Sylius\Bundle\OrderBundle\Controller\AddToCartCommand;
use Sylius\Bundle\ShopBundle\Form\Type\AddToCartType;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;

/** "Sold by whole cases" on the shop add to cart form: default quantity, step, server-side check. */
final class AddToCartCaseMultipleTest extends KernelTestCase
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

    public function testTheFormStartsAtOneCaseAndRefusesPartialCases(): void
    {
        $pallet = $this->catalog->pallet();
        $product = $this->catalog->variant('WATER', [[$pallet, 480, null]], 9300, 6, false, true)->getProduct();
        self::assertInstanceOf(ProductInterface::class, $product);

        $form = $this->form($product);
        $view = $form->createView();
        $vars = (array) $view->children['cartItem']->children['quantity']->vars; // untyped on Symfony 6.4
        self::assertEquals(6, $vars['value']);
        self::assertIsArray($vars['attr']);
        self::assertSame(6, $vars['attr']['step'] ?? null);
        self::assertSame(6, $vars['attr']['min'] ?? null);

        $form->submit(['cartItem' => ['quantity' => '4']]);
        self::assertFalse($form->isValid());
        self::assertStringContainsString('multiple of 6', (string) $form->getErrors(true));

        // Above the core line limit (9999), cut to 9999 by the core: the message says why.
        self::assertSame(9996, $vars['attr']['max'] ?? null);
        $form = $this->form($product);
        $form->submit(['cartItem' => ['quantity' => '15840']]);
        self::assertFalse($form->isValid());
        self::assertStringContainsString('At most 9999 units per cart line', (string) $form->getErrors(true));
        self::assertStringContainsString('order at most 9996', (string) $form->getErrors(true));

        $form = $this->form($product);
        $form->submit(['cartItem' => ['quantity' => '12']]);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testOtherProductsKeepTheDefaultQuantity(): void
    {
        $pallet = $this->catalog->pallet();
        $product = $this->catalog->variant('LOOSE', [[$pallet, 480, null]], 9300, 6)->getProduct();
        self::assertInstanceOf(ProductInterface::class, $product);

        $form = $this->form($product);
        self::assertEquals(1, ((array) $form->createView()->children['cartItem']->children['quantity']->vars)['value'] ?? null);
        $form->submit(['cartItem' => ['quantity' => '4']]);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    private function form(ProductInterface $product): FormInterface
    {
        $itemFactory = self::getContainer()->get('sylius.factory.order_item');
        self::assertInstanceOf(CartItemFactoryInterface::class, $itemFactory);
        $formFactory = self::getContainer()->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);
        $cart = $this->catalog->cart([]);

        return $formFactory->create(AddToCartType::class, new AddToCartCommand($cart, $itemFactory->createForProduct($product)), ['product' => $product, 'csrf_protection' => false]);
    }
}
