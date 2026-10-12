<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Tmonier\SyliusContainerFillPlugin\Form\Logistics\VariantLogisticsData;
use Tmonier\SyliusContainerFillPlugin\Form\Logistics\VariantLogisticsManager;
use Tmonier\SyliusContainerFillPlugin\Form\Logistics\VariantLogisticsType;

/** "Logistics" tab of the admin variant form: an unmapped field, stored in the plugin tables. */
final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    public const FIELD = 'tmonierContainerFill';

    public function __construct(private readonly VariantLogisticsManager $manager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $variant = $event->getData();
                $event->getForm()->add(self::FIELD, VariantLogisticsType::class, [
                    'mapped' => false,
                    'data' => $variant instanceof ProductVariantInterface ? $this->manager->load($variant) : new VariantLogisticsData(),
                ]);
            })
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
                $variant = $event->getData();
                $form = $event->getForm();
                if (!$variant instanceof ProductVariantInterface || !$form->has(self::FIELD) || !$form->isValid()) {
                    return;
                }
                $data = $form->get(self::FIELD)->getData();
                if ($data instanceof VariantLogisticsData) {
                    $this->manager->stage($variant, $data);
                }
            }, -100)
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }
}
