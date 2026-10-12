<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\Units;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRule;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerType;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeTranslation;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeTranslationInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshot;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogistics;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacity;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerRuleRepository;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepository;
use Tmonier\SyliusContainerFillPlugin\Repository\OrderLoadPlanSnapshotRepository;
use Tmonier\SyliusContainerFillPlugin\Repository\ProductVariantLogisticsRepository;
use Tmonier\SyliusContainerFillPlugin\Repository\VariantContainerCapacityRepository;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_container_fill');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('units')
                    ->info('Units of the Sylius variant fields width, height, depth and weight (Sylius stores bare numbers). The plugin stores millimetres and grams.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('length')->values(array_keys(Units::LENGTH_UNITS))->defaultValue('cm')->end()
                        ->enumNode('weight')->values(array_keys(Units::WEIGHT_UNITS))->defaultValue('kg')->end()
                    ->end()
                ->end()
                ->scalarNode('override_role')
                    ->info('Role (or security attribute) letting an administrator complete an order that does not meet the fill rules (admin order creation, admin API); logged and recorded on the load plan. Nobody has it by default: grant it through role_hierarchy. Null disables overrides.')
                    ->defaultValue('ROLE_TMONIER_CONTAINER_FILL_OVERRIDE')
                ->end()
                ->booleanNode('workflow_guard')
                    ->info('Also block the "complete" transition of the sylius_order_checkout workflow (Symfony Workflow adapter, the Sylius 2 default).')
                    ->defaultTrue()
                ->end()
                ->booleanNode('cart_gauge')
                    ->info('Show the fill gauge in the cart and disable the checkout button while the rules are not met.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('product_preview')
                    ->info('Show the capacity of the variant and the current fill of the cart on product pages.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('container_type')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(ContainerType::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(ContainerTypeInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(ContainerTypeRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                                ->arrayNode('translation')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->arrayNode('classes')
                                            ->addDefaultsIfNotSet()
                                            ->children()
                                                ->scalarNode('model')->defaultValue(ContainerTypeTranslation::class)->cannotBeEmpty()->end()
                                                ->scalarNode('interface')->defaultValue(ContainerTypeTranslationInterface::class)->cannotBeEmpty()->end()
                                                ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                                ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('container_rule')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(ContainerRule::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(ContainerRuleInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(ContainerRuleRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('variant_logistics')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(ProductVariantLogistics::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(ProductVariantLogisticsInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(ProductVariantLogisticsRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('variant_capacity')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(VariantContainerCapacity::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(VariantContainerCapacityInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(VariantContainerCapacityRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('order_load_plan')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(OrderLoadPlanSnapshot::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(OrderLoadPlanSnapshotInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(OrderLoadPlanSnapshotRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
