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
        self::assertSame('6', (string) $view['cartItem']['quantity']->vars['value']);
        self::assertSame(6, $view['cartItem']['quantity']->vars['attr']['step']);
        self::assertSame(6, $view['cartItem']['quantity']->vars['attr']['min']);

        $form->submit(['cartItem' => ['quantity' => '4']]);
        self::assertFalse($form->isValid());
        self::assertStringContainsString('multiple of 6', (string) $form->getErrors(true)->current()->getMessage());

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
        self::assertSame('1', (string) $form->createView()['cartItem']['quantity']->vars['value']);
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
