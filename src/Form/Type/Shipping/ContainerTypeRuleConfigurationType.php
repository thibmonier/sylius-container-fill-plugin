<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type\Shipping;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;

final class ContainerTypeRuleConfigurationType extends AbstractType
{
    public function __construct(private readonly ContainerTypeRepositoryInterface $containerTypeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->containerTypeRepository->findAllOrdered() as $type) {
            $choices[(string) ($type->getName() ?? $type->getCode())] = (string) $type->getCode();
        }

        $builder->add('container_types', ChoiceType::class, [
            'label' => 'tmonier_container_fill.ui.container_types',
            'choices' => $choices,
            'multiple' => true,
            'expanded' => true,
            'constraints' => [new Count(min: 1, groups: ['sylius'])],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_container_fill_shipping_rule_container_type';
    }
}
