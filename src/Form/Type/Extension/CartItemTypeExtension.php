<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type\Extension;

use Sylius\Bundle\OrderBundle\Form\Type\CartItemType;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;

/**
 * Quantity fields of variants sold by whole cases (add to cart and cart page): step and
 * minimum of one case; a new add to cart line starts at one case instead of 1. The server
 * side check is the CaseMultiple constraint.
 */
final class CartItemTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly VariantLogisticsProviderInterface $logisticsProvider,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly int $quantityLimit = 9999,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $item = $event->getData();
            $form = $event->getForm();
            if (!$item instanceof OrderItemInterface || !$item->getVariant() instanceof \Sylius\Component\Core\Model\ProductVariantInterface || !$form->has('quantity')) {
                return;
            }
            $logistics = $this->logisticsProvider->getForVariant($item->getVariant());
            if (!$logistics->isCaseMultipleRequired()) {
                return;
            }
            $case = $logistics->unitsPerCase();
            if (null === $item->getId() && 0 !== $item->getQuantity() % $case) {
                $this->quantityModifier->modify($item, max($case, (int) ceil($item->getQuantity() / $case) * $case));
            }

            $field = $form->get('quantity')->getConfig();
            $fieldOptions = $field->getOptions();
            $attr = \is_array($fieldOptions['attr'] ?? null) ? $fieldOptions['attr'] : [];
            $form->add('quantity', IntegerType::class, [
                'label' => $fieldOptions['label'] ?? 'sylius.ui.quantity',
                // The core caps a line at its quantity limit: the largest whole-case quantity below it.
                'attr' => ['min' => $case, 'step' => $case, 'max' => max($case, intdiv($this->quantityLimit, $case) * $case)] + $attr,
                'help' => 'tmonier_container_fill.ui.whole_cases_help',
                'help_translation_parameters' => ['%units%' => $case],
            ]);
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [CartItemType::class];
    }
}
