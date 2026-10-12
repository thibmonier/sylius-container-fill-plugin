<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusContainerFillPlugin\Cart\CartQuantityAdjuster;
use Tmonier\SyliusContainerFillPlugin\Checkout\CheckoutCompleteGuardListener;
use Tmonier\SyliusContainerFillPlugin\Checkout\CheckoutEntrySubscriber;
use Tmonier\SyliusContainerFillPlugin\Checkout\FillOverrideChecker;
use Tmonier\SyliusContainerFillPlugin\Checkout\LoadPlanSnapshotListener;
use Tmonier\SyliusContainerFillPlugin\Command\ExportCapacitiesCommand;
use Tmonier\SyliusContainerFillPlugin\Command\ImportCapacitiesCommand;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvExporter;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvImporter;
use Tmonier\SyliusContainerFillPlugin\EventListener\CacheInvalidator;
use Tmonier\SyliusContainerFillPlugin\Fixture\ContainerFillDemoFixture;
use Tmonier\SyliusContainerFillPlugin\Fixture\ContainerTypePresetFixture;
use Tmonier\SyliusContainerFillPlugin\Form\Logistics\VariantLogisticsManager;
use Tmonier\SyliusContainerFillPlugin\Form\Logistics\VariantLogisticsType;
use Tmonier\SyliusContainerFillPlugin\Form\Type\ContainerRuleType;
use Tmonier\SyliusContainerFillPlugin\Form\Type\ContainerTypeTranslationType;
use Tmonier\SyliusContainerFillPlugin\Form\Type\ContainerTypeType;
use Tmonier\SyliusContainerFillPlugin\Form\Type\Extension\CartItemTypeExtension;
use Tmonier\SyliusContainerFillPlugin\Form\Type\Extension\ProductVariantTypeExtension;
use Tmonier\SyliusContainerFillPlugin\Form\Type\Shipping\ContainerTypeRuleConfigurationType;
use Tmonier\SyliusContainerFillPlugin\Logistics\Units;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProvider;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;
use Tmonier\SyliusContainerFillPlugin\Menu\AdminMenuListener;
use Tmonier\SyliusContainerFillPlugin\Planner\LoadPlannerInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\SlotWeightPlanner;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillChecker;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleEvaluator;
use Tmonier\SyliusContainerFillPlugin\Rule\ScopeMatcher;
use Tmonier\SyliusContainerFillPlugin\Rule\SuggestionFinder;
use Tmonier\SyliusContainerFillPlugin\Shipping\ContainerTypeRuleChecker;
use Tmonier\SyliusContainerFillPlugin\Shipping\PerContainerRateCalculator;
use Tmonier\SyliusContainerFillPlugin\Twig\Component\ContainerGaugeComponent;
use Tmonier\SyliusContainerFillPlugin\Twig\ContainerFillExtension;
use Tmonier\SyliusContainerFillPlugin\Validator\CaseMultipleValidator;
use Tmonier\SyliusContainerFillPlugin\Validator\OrderContainerFillSatisfiedValidator;
use Tmonier\SyliusContainerFillPlugin\Validator\ValidContainerRuleValidator;
use Tmonier\SyliusContainerFillPlugin\Validator\ValidContainerTypeValidator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Planner and rules
    $services->set('tmonier_container_fill.units', Units::class)
        ->args([param('tmonier_container_fill.units.length'), param('tmonier_container_fill.units.weight')]);

    $services->set('tmonier_container_fill.planner', SlotWeightPlanner::class);
    $services->alias(LoadPlannerInterface::class, 'tmonier_container_fill.planner');

    $services->set('tmonier_container_fill.rule_evaluator', RuleEvaluator::class);
    $services->set('tmonier_container_fill.scope_matcher', ScopeMatcher::class);
    $services->set('tmonier_container_fill.suggestion_finder', SuggestionFinder::class)
        ->args([service('tmonier_container_fill.planner'), service('tmonier_container_fill.rule_evaluator')]);

    $services->set('tmonier_container_fill.logistics_provider', VariantLogisticsProvider::class)
        ->args([
            service('tmonier_container_fill.repository.variant_logistics'),
            service('tmonier_container_fill.repository.variant_capacity'),
            service('tmonier_container_fill.units'),
        ]);
    $services->alias(VariantLogisticsProviderInterface::class, 'tmonier_container_fill.logistics_provider');

    $services->set('tmonier_container_fill.cart_fill_checker', CartFillChecker::class)
        ->args([
            service('tmonier_container_fill.repository.container_rule'),
            service('tmonier_container_fill.logistics_provider'),
            service('tmonier_container_fill.planner'),
            service('tmonier_container_fill.rule_evaluator'),
            service('tmonier_container_fill.suggestion_finder'),
            service('tmonier_container_fill.scope_matcher'),
        ])
        ->tag('kernel.reset', ['method' => 'reset'])
        ->public();
    $services->alias(CartFillCheckerInterface::class, 'tmonier_container_fill.cart_fill_checker')->public();

    $services->set('tmonier_container_fill.cache_invalidator', CacheInvalidator::class)
        ->args([service('tmonier_container_fill.cart_fill_checker')])
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);

    // Checkout
    $services->set('tmonier_container_fill.override_checker', FillOverrideChecker::class)
        ->args([service('security.token_storage'), service('security.authorization_checker'), param('tmonier_container_fill.override_role')]);

    $services->set('tmonier_container_fill.checkout_entry_subscriber', CheckoutEntrySubscriber::class)
        ->args([service('sylius.context.cart'), service('tmonier_container_fill.cart_fill_checker'), service('router')])
        ->tag('kernel.event_subscriber');

    $services->set('tmonier_container_fill.checkout_complete_guard', CheckoutCompleteGuardListener::class)
        ->args([service('tmonier_container_fill.cart_fill_checker'), service('tmonier_container_fill.override_checker')]);

    $services->set('tmonier_container_fill.load_plan_snapshot_listener', LoadPlanSnapshotListener::class)
        ->args([
            service('tmonier_container_fill.cart_fill_checker'),
            service('tmonier_container_fill.override_checker'),
            service('tmonier_container_fill.factory.order_load_plan'),
            service('tmonier_container_fill.repository.order_load_plan'),
            service('doctrine.orm.entity_manager'),
            service('logger'),
        ])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order_checkout.completed.complete', 'priority' => 0])
        ->tag('monolog.logger', ['channel' => 'tmonier_container_fill']);

    // Validators
    $services->set('tmonier_container_fill.validator.order_satisfied', OrderContainerFillSatisfiedValidator::class)
        ->args([
            service('tmonier_container_fill.cart_fill_checker'),
            service('sylius.repository.order'),
            service('tmonier_container_fill.override_checker'),
            service('logger'),
        ])
        ->tag('validator.constraint_validator', ['alias' => 'tmonier_container_fill_order_satisfied'])
        ->tag('monolog.logger', ['channel' => 'tmonier_container_fill']);

    $services->set('tmonier_container_fill.validator.case_multiple', CaseMultipleValidator::class)
        ->args([service('tmonier_container_fill.logistics_provider'), service('sylius.repository.product_variant'), service('sylius.repository.order_item'), param('sylius.order_item_quantity_modifier.limit')])
        ->tag('validator.constraint_validator', ['alias' => 'tmonier_container_fill_case_multiple']);

    $services->set('tmonier_container_fill.validator.container_type', ValidContainerTypeValidator::class)
        ->tag('validator.constraint_validator');
    $services->set('tmonier_container_fill.validator.container_rule', ValidContainerRuleValidator::class)
        ->tag('validator.constraint_validator');

    // Cart and shop
    $services->set('tmonier_container_fill.cart_quantity_adjuster', CartQuantityAdjuster::class)
        ->args([
            service('sylius.modifier.order_item_quantity'),
            service('event_dispatcher'),
            service('sylius.manager.order'),
            param('sylius.order_item_quantity_modifier.limit'),
        ]);

    $services->set('tmonier_container_fill.twig.component.gauge', ContainerGaugeComponent::class)
        ->args([
            service('sylius.context.cart'),
            service('tmonier_container_fill.cart_fill_checker'),
            service('tmonier_container_fill.cart_quantity_adjuster'),
            service('router'),
            service('request_stack'),
        ])
        ->call('setLiveResponder', [service('ux.live_component.live_responder')])
        ->tag('sylius.live_component.shop', [
            'key' => 'tmonier_container_fill:gauge',
            'template' => '@TmonierSyliusContainerFillPlugin/shop/gauge.html.twig',
        ]);

    $services->set('tmonier_container_fill.twig.extension', ContainerFillExtension::class)
        ->args([
            service('tmonier_container_fill.cart_fill_checker'),
            service('tmonier_container_fill.logistics_provider'),
            service('tmonier_container_fill.repository.container_type'),
            service('tmonier_container_fill.repository.order_load_plan'),
            param('tmonier_container_fill.cart_gauge'),
            param('tmonier_container_fill.product_preview'),
        ])
        ->tag('twig.extension');

    // Shipping (attributes are not autoconfigured here: tags written explicitly)
    $services->set('tmonier_container_fill.shipping.per_container_calculator', PerContainerRateCalculator::class)
        ->args([service('tmonier_container_fill.cart_fill_checker')])
        ->tag('sylius.shipping_calculator', [
            'calculator' => PerContainerRateCalculator::TYPE,
            'label' => 'tmonier_container_fill.shipping.per_container',
            'form_type' => \Sylius\Bundle\CoreBundle\Form\Type\Shipping\Calculator\ChannelBasedFlatRateConfigurationType::class,
        ]);

    $services->set('tmonier_container_fill.shipping.container_type_rule_checker', ContainerTypeRuleChecker::class)
        ->args([service('tmonier_container_fill.cart_fill_checker')])
        ->tag('sylius.shipping_method_rule_checker', [
            'type' => ContainerTypeRuleChecker::TYPE,
            'label' => 'tmonier_container_fill.shipping.container_type_rule',
            'form_type' => ContainerTypeRuleConfigurationType::class,
        ]);

    $services->set('tmonier_container_fill.form.type.shipping_rule_container_type', ContainerTypeRuleConfigurationType::class)
        ->args([service('tmonier_container_fill.repository.container_type')])
        ->tag('form.type');

    // Admin
    $services->set('tmonier_container_fill.form.type.container_type', ContainerTypeType::class)
        ->args([param('tmonier_container_fill.model.container_type.class'), ['sylius']])
        ->tag('form.type');
    $services->set('tmonier_container_fill.form.type.container_type_translation', ContainerTypeTranslationType::class)
        ->args([param('tmonier_container_fill.model.container_type_translation.class'), ['sylius']])
        ->tag('form.type');
    $services->set('tmonier_container_fill.form.type.container_rule', ContainerRuleType::class)
        ->args([param('tmonier_container_fill.model.container_rule.class'), ['sylius'], param('tmonier_container_fill.model.container_type.class')])
        ->tag('form.type');

    $services->set('tmonier_container_fill.form.type.variant_logistics', VariantLogisticsType::class)
        ->args([service('tmonier_container_fill.repository.container_type')])
        ->tag('form.type');

    $services->set('tmonier_container_fill.variant_logistics_manager', VariantLogisticsManager::class)
        ->args([
            service('tmonier_container_fill.repository.variant_logistics'),
            service('tmonier_container_fill.repository.variant_capacity'),
            service('tmonier_container_fill.repository.container_type'),
            service('tmonier_container_fill.factory.variant_logistics'),
            service('tmonier_container_fill.factory.variant_capacity'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag('kernel.event_listener', ['event' => 'sylius.product_variant.pre_create', 'method' => 'onPreSave'])
        ->tag('kernel.event_listener', ['event' => 'sylius.product_variant.pre_update', 'method' => 'onPreSave'])
        // Simple products: the variant form is embedded in the product form.
        ->tag('kernel.event_listener', ['event' => 'sylius.product.pre_create', 'method' => 'onPreSave'])
        ->tag('kernel.event_listener', ['event' => 'sylius.product.pre_update', 'method' => 'onPreSave'])
        ->tag('kernel.reset', ['method' => 'reset'])
        ->public();

    $services->set('tmonier_container_fill.form.extension.cart_item', CartItemTypeExtension::class)
        ->args([service('tmonier_container_fill.logistics_provider'), service('sylius.modifier.order_item_quantity'), param('sylius.order_item_quantity_modifier.limit')])
        ->tag('form.type_extension');

    $services->set('tmonier_container_fill.form.extension.product_variant', ProductVariantTypeExtension::class)
        ->args([service('tmonier_container_fill.variant_logistics_manager')])
        ->tag('form.type_extension');

    $services->set('tmonier_container_fill.menu.admin', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main']);

    // CSV
    $services->set('tmonier_container_fill.csv.importer', CapacityCsvImporter::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius.repository.product_variant'),
            service('tmonier_container_fill.repository.container_type'),
            service('tmonier_container_fill.repository.variant_logistics'),
            service('tmonier_container_fill.repository.variant_capacity'),
            service('tmonier_container_fill.factory.variant_logistics'),
            service('tmonier_container_fill.factory.variant_capacity'),
        ])
        ->public();
    $services->set('tmonier_container_fill.csv.exporter', CapacityCsvExporter::class)
        ->args([service('tmonier_container_fill.repository.variant_capacity'), service('tmonier_container_fill.repository.variant_logistics')]);

    $services->set('tmonier_container_fill.command.import', ImportCapacitiesCommand::class)
        ->args([service('tmonier_container_fill.csv.importer')])
        ->tag('console.command');
    $services->set('tmonier_container_fill.command.export', ExportCapacitiesCommand::class)
        ->args([service('tmonier_container_fill.csv.exporter')])
        ->tag('console.command');

    // Fixtures
    $services->set('tmonier_container_fill.fixture.container_types', ContainerTypePresetFixture::class)
        ->args([service('doctrine.orm.entity_manager'), service('tmonier_container_fill.factory.container_type'), service('tmonier_container_fill.repository.container_type'), service('sylius.repository.locale')])
        ->tag('sylius_fixtures.fixture');
    $services->set('tmonier_container_fill.fixture.demo', ContainerFillDemoFixture::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius.repository.channel'),
            service('sylius.factory.product'),
            service('sylius.factory.product_variant'),
            service('sylius.factory.channel_pricing'),
            service('sylius.repository.product'),
            service('sylius.factory.taxon'),
            service('sylius.factory.product_taxon'),
            service('tmonier_container_fill.repository.container_type'),
            service('tmonier_container_fill.factory.variant_logistics'),
            service('tmonier_container_fill.factory.variant_capacity'),
            service('tmonier_container_fill.factory.container_rule'),
            service('sylius.factory.shop_user'),
            service('sylius.factory.customer'),
            service('sylius.repository.shop_user'),
        ])
        ->tag('sylius_fixtures.fixture');
};
