<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Logistics;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;
use Tmonier\SyliusContainerFillPlugin\Form\Type\KilogramType;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;

final class VariantLogisticsType extends AbstractType
{
    public function __construct(private readonly ContainerTypeRepositoryInterface $containerTypeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unitsPerCase', IntegerType::class, [
                'label' => 'tmonier_container_fill.form.units_per_case',
                'help' => 'tmonier_container_fill.form.units_per_case_help',
                'empty_data' => '1',
                'attr' => ['min' => 1],
                'constraints' => [new Range(min: 1, max: 100000, groups: ['sylius'])],
            ])
            ->add('caseWeight', KilogramType::class, [
                'label' => 'tmonier_container_fill.form.case_weight',
                'help' => 'tmonier_container_fill.form.case_weight_help',
                'required' => false,
                'constraints' => [new Range(min: 1, max: 100000000, groups: ['sylius'])],
            ])
            ->add('layerHeight', IntegerType::class, [
                'label' => 'tmonier_container_fill.form.layer_height',
                'help' => 'tmonier_container_fill.form.layer_height_help',
                'required' => false,
                'attr' => ['min' => 1],
                'constraints' => [new Range(min: 1, max: 100000, groups: ['sylius'])],
            ])
            ->add('stackable', CheckboxType::class, [
                'label' => 'tmonier_container_fill.form.stackable',
                'help' => 'tmonier_container_fill.form.stackable_help',
                'required' => false,
            ])
            ->add('ownPallet', CheckboxType::class, [
                'label' => 'tmonier_container_fill.form.own_pallet',
                'help' => 'tmonier_container_fill.form.own_pallet_help',
                'required' => false,
            ])
            ->add('caseMultipleRequired', CheckboxType::class, [
                'label' => 'tmonier_container_fill.form.case_multiple_required',
                'help' => 'tmonier_container_fill.form.case_multiple_required_help',
                'required' => false,
            ])
        ;

        $capacities = $builder->create('capacities', FormType::class, ['label' => false, 'compound' => true]);
        foreach ($this->containerTypeRepository->findAllOrdered() as $type) {
            $row = $builder->create('t' . (string) $type->getId(), FormType::class, [
                'label' => (string) ($type->getName() ?? $type->getCode()),
                'compound' => true,
            ]);
            $row
                ->add('quantity', IntegerType::class, [
                    'label' => 'tmonier_container_fill.form.capacity',
                    'required' => false,
                    'attr' => ['min' => 1],
                    'constraints' => [new Range(min: 1, max: 100000000, groups: ['sylius'])],
                ])
                ->add('unitsPerLayer', IntegerType::class, [
                    'label' => 'tmonier_container_fill.form.units_per_layer',
                    'required' => false,
                    'attr' => ['min' => 1],
                    'constraints' => [new Range(min: 1, max: 100000000, groups: ['sylius'])],
                ])
            ;
            $capacities->add($row);
        }
        $builder->add($capacities);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VariantLogisticsData::class,
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_container_fill_variant_logistics';
    }
}
