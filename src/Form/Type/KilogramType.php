<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** A weight stored in grams (integer), entered in kilograms. */
final class KilogramType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $grams): ?float => is_numeric($grams) ? (int) $grams / 1000 : null,
            static fn (mixed $kilograms): ?int => is_numeric($kilograms) ? (int) round((float) $kilograms * 1000) : null,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'scale' => 3,
            'html5' => true,
            'attr' => ['min' => 0, 'step' => 0.001],
        ]);
    }

    public function getParent(): string
    {
        return NumberType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_container_fill_kilogram';
    }
}
