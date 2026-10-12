<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class ContainerTypeTranslationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'sylius.ui.name']);
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_container_fill_container_type_translation';
    }
}
