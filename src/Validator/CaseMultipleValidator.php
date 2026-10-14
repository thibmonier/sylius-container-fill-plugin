<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Validator;

use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Sylius\Bundle\ApiBundle\Command\Cart\ChangeItemQuantityInCart;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\OrderItemRepositoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;

/**
 * "Sold by whole cases": the quantity is a multiple of the case. Checked on the cart item
 * (cart page), the shop add to cart command (the added quantity) and the shop API add item /
 * change quantity commands, which Sylius validates without cascading to the order item.
 */
final class CaseMultipleValidator extends ConstraintValidator
{
    /**
     * @param RepositoryInterface<ProductVariantInterface> $variantRepository
     * @param OrderItemRepositoryInterface<OrderItemInterface> $orderItemRepository
     */
    public function __construct(
        private readonly VariantLogisticsProviderInterface $logisticsProvider,
        private readonly RepositoryInterface $variantRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly int $quantityLimit = 9999,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CaseMultiple) {
            throw new UnexpectedTypeException($constraint, CaseMultiple::class);
        }

        [$variant, $quantity, $path] = match (true) {
            $value instanceof OrderItemInterface => $this->fromOrderItem($value),
            $value instanceof AddToCartCommandInterface => $this->fromAddToCart($value),
            $value instanceof AddItemToCart => [$this->variantRepository->findOneBy(['code' => $value->productVariantCode]), $value->quantity, 'quantity'],
            $value instanceof ChangeItemQuantityInCart => $this->fromChangeQuantity($value),
            default => [null, 0, ''],
        };
        if (!$variant instanceof ProductVariantInterface || $quantity < 1) {
            return;
        }

        $logistics = $this->logisticsProvider->getForVariant($variant);
        if (!$logistics->isCaseMultipleRequired() || 0 === $quantity % $logistics->unitsPerCase()) {
            return;
        }

        // The core silently cuts a line to its quantity limit: say so instead of "not a multiple".
        $case = $logistics->unitsPerCase();
        $this->context
            ->buildViolation($quantity >= $this->quantityLimit ? $constraint->limitMessage : $constraint->message)
            ->setParameter('%multiple%', (string) $case)
            ->setParameter('%limit%', (string) $this->quantityLimit)
            ->setParameter('%maximum%', (string) (intdiv($this->quantityLimit, $case) * $case))
            ->atPath($path)
            ->addViolation()
        ;
    }

    /** @return array{?ProductVariantInterface, int, string} */
    private function fromOrderItem(OrderItemInterface $item): array
    {
        $order = $item->getOrder();
        if ($order instanceof \Sylius\Component\Order\Model\OrderInterface && OrderInterface::STATE_CART !== $order->getState()) {
            return [null, 0, ''];
        }

        return [$item->getVariant(), $item->getQuantity(), 'quantity'];
    }

    /** @return array{?ProductVariantInterface, int, string} */
    private function fromAddToCart(AddToCartCommandInterface $command): array
    {
        $item = $command->getCartItem();

        return $item instanceof OrderItemInterface ? [$item->getVariant(), $item->getQuantity(), 'cartItem.quantity'] : [null, 0, ''];
    }

    /** @return array{?ProductVariantInterface, int, string} */
    private function fromChangeQuantity(ChangeItemQuantityInCart $command): array
    {
        $item = $this->orderItemRepository->findOneByIdAndCartTokenValue($command->orderItemId, $command->orderTokenValue);

        return $item instanceof OrderItemInterface ? [$item->getVariant(), $command->quantity, 'quantity'] : [null, 0, ''];
    }
}
