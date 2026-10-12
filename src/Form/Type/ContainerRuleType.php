<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Type;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\AdminBundle\Form\Type\ProductAutocompleteType;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonAutocompleteType;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\CustomerBundle\Form\Type\CustomerGroupChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

final class ContainerRuleType extends AbstractResourceType
{
    /** @param list<string> $validationGroups */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly string $containerTypeClass,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'sylius.ui.name'])
            ->add('channel', ChannelChoiceType::class, [
                'label' => 'sylius.ui.channel',
                'placeholder' => false,
            ])
            ->add('customerGroup', CustomerGroupChoiceType::class, [
                'label' => 'sylius.ui.customer_group',
                'required' => false,
                'placeholder' => 'tmonier_container_fill.form.all_customers',
                'help' => 'tmonier_container_fill.form.customer_group_help',
            ])
            ->add('scope', ChoiceType::class, [
                'label' => 'tmonier_container_fill.form.scope',
                'choices' => array_combine(
                    array_map(static fn (string $scope): string => 'tmonier_container_fill.scope.' . $scope, ContainerRuleInterface::SCOPES),
                    ContainerRuleInterface::SCOPES,
                ),
                'expanded' => true,
            ])
            ->add('taxon', TaxonAutocompleteType::class, [
                'label' => 'sylius.ui.taxon',
                'required' => false,
                'help' => 'tmonier_container_fill.form.taxon_help',
            ])
            ->add('product', ProductAutocompleteType::class, [
                'label' => 'sylius.ui.product',
                'required' => false,
            ])
            ->add('containerTypes', EntityType::class, [
                'label' => 'tmonier_container_fill.ui.container_types',
                'help' => 'tmonier_container_fill.form.container_types_help',
                'class' => $this->containerTypeClass,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('o')->addOrderBy('o.position', 'ASC'),
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'tmonier_container_fill.form.mode',
                'choices' => array_combine(
                    array_map(static fn (string $mode): string => 'tmonier_container_fill.mode.' . $mode, ContainerRuleInterface::MODES),
                    ContainerRuleInterface::MODES,
                ),
                'expanded' => true,
            ])
            ->add('threshold', IntegerType::class, [
                'label' => 'tmonier_container_fill.form.threshold',
                'help' => 'tmonier_container_fill.form.threshold_help',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                $rule = $event->getData();
                if (!$rule instanceof ContainerRuleInterface) {
                    return;
                }
                // Only the target of the scope is kept.
                if (ContainerRuleInterface::SCOPE_TAXON !== $rule->getScope()) {
                    $rule->setTaxon(null);
                }
                if (ContainerRuleInterface::SCOPE_PRODUCT !== $rule->getScope()) {
                    $rule->setProduct(null);
                }
            })
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_container_fill_container_rule';
    }
}
