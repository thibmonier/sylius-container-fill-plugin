<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;

final class ContainerTypeType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $millimetres = static fn (string $label, string $help = ''): array => [
            'label' => $label,
            'required' => false,
            'help' => '' === $help ? null : $help,
            'attr' => ['min' => 1, 'inputmode' => 'numeric'],
        ];

        $builder
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => ContainerTypeTranslationType::class,
                'label' => 'sylius.ui.name',
            ])
            ->add('kind', ChoiceType::class, [
                'label' => 'tmonier_container_fill.form.kind',
                'choices' => array_combine(
                    array_map(static fn (string $kind): string => 'tmonier_container_fill.kind.' . $kind, ContainerTypeInterface::KINDS),
                    ContainerTypeInterface::KINDS,
                ),
            ])
            ->add('innerLength', IntegerType::class, $millimetres('tmonier_container_fill.form.inner_length'))
            ->add('innerWidth', IntegerType::class, $millimetres('tmonier_container_fill.form.inner_width'))
            ->add('innerHeight', IntegerType::class, $millimetres('tmonier_container_fill.form.inner_height'))
            ->add('maxLoadHeight', IntegerType::class, $millimetres('tmonier_container_fill.form.max_load_height', 'tmonier_container_fill.form.max_load_height_help'))
            ->add('maxPayload', KilogramType::class, [
                'label' => 'tmonier_container_fill.form.max_payload',
                'help' => 'tmonier_container_fill.form.max_payload_help',
                'empty_data' => '0',
            ])
            ->add('slots', IntegerType::class, [
                'label' => 'tmonier_container_fill.form.slots',
                'help' => 'tmonier_container_fill.form.slots_help',
                'empty_data' => '0',
                'attr' => ['min' => 1],
            ])
            ->add('nestedType', EntityType::class, [
                'label' => 'tmonier_container_fill.form.nested_type',
                'help' => 'tmonier_container_fill.form.nested_type_help',
                'class' => $this->dataClass,
                'required' => false,
                'placeholder' => 'tmonier_container_fill.form.no_nested_type',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('o')
                    ->andWhere('o.slots = 1')
                    ->addOrderBy('o.position', 'ASC'),
            ])
            ->add('volumeEfficiency', IntegerType::class, [
                'label' => 'tmonier_container_fill.form.volume_efficiency',
                'help' => 'tmonier_container_fill.form.volume_efficiency_help',
                'empty_data' => '0',
                'attr' => ['min' => 1, 'max' => 100],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'sylius.ui.position',
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('indicative', CheckboxType::class, [
                'label' => 'tmonier_container_fill.form.indicative',
                'help' => 'tmonier_container_fill.form.indicative_help',
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;

        // Non-nullable string properties: no choice submitted becomes a validation error, not a type error.
        $builder->get('kind')->addModelTransformer(new CallbackTransformer(static fn (mixed $value): mixed => $value, static fn (mixed $value): string => \is_string($value) ? $value : ''));
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_container_fill_container_type';
    }
}
