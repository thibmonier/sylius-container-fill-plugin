<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Addressing\Model\ZoneMemberInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Webmozart\Assert\Assert;

/**
 * Data created by the tests themselves (inside their rolled-back transaction), so that no test
 * depends on the random Sylius fixtures: container types, logistics data, rules, products,
 * customers, carts, a zone with shipping and payment methods. Codes get a random suffix.
 */
final class TestCatalog
{
    public readonly string $suffix;

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->suffix = strtoupper(bin2hex(random_bytes(3)));
    }

    public function channel(): ChannelInterface
    {
        $repository = $this->entityManager()->getRepository(ChannelInterface::class);
        $channel = $repository->findOneBy(['hostname' => 'localhost']) ?? $repository->findOneBy(['enabled' => true]);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        return $channel;
    }

    /** A pallet type: 1 place, payload in kg. */
    public function pallet(string $code = 'PAL', int $payloadKg = 1000): ContainerTypeInterface
    {
        return $this->containerType($code, ContainerTypeInterface::KIND_PALLET, 1, $payloadKg, null);
    }

    /** A pallet planned by layers (1.1): load height in mm and/or a maximum number of layers. */
    public function layerPallet(string $code = 'LPAL', int $payloadKg = 1000, ?int $stackHeight = 1600, ?int $maxLayers = null): ContainerTypeInterface
    {
        $pallet = $this->pallet($code, $payloadKg);
        $pallet->setLayerMode(true);
        $pallet->setMaxLoadHeight($stackHeight);
        $pallet->setMaxLayers($maxLayers);
        $this->entityManager()->flush();

        return $pallet;
    }

    /** Layer data of a variant created by variant(): layer height in mm, stackable. */
    public function layers(ProductVariantInterface $variant, int $layerHeight, bool $stackable = true): void
    {
        $logistics = $this->entityManager()->getRepository(ProductVariantLogisticsInterface::class)->findOneBy(['variant' => $variant]);
        Assert::isInstanceOf($logistics, ProductVariantLogisticsInterface::class);
        $logistics->setLayerHeight($layerHeight);
        $logistics->setStackable($stackable);
        $this->entityManager()->flush();
    }

    /** A truck whose places hold $nested. */
    public function truck(ContainerTypeInterface $nested, string $code = 'TRUCK', int $places = 33, int $payloadKg = 24000): ContainerTypeInterface
    {
        return $this->containerType($code, ContainerTypeInterface::KIND_TRUCK, $places, $payloadKg, $nested);
    }

    /**
     * A variant of a new product with logistics data and a capacity per container type.
     *
     * @param array<int, array{ContainerTypeInterface, int, ?int}> $capacities type, units per container, units per layer
     */
    public function variant(string $code, array $capacities = [], ?int $caseWeight = 10000, int $unitsPerCase = 1, bool $ownPallet = false, bool $caseMultiple = false, ?TaxonInterface $taxon = null, int $price = 1000): ProductVariantInterface
    {
        $variant = $this->plainVariant($code, $price, $taxon);

        /** @var FactoryInterface<ProductVariantLogisticsInterface> $logisticsFactory */
        $logisticsFactory = $this->container->get('tmonier_container_fill.factory.variant_logistics');
        $logistics = $logisticsFactory->createNew();
        $logistics->setVariant($variant);
        $logistics->setUnitsPerCase($unitsPerCase);
        $logistics->setCaseWeight($caseWeight);
        $logistics->setOwnPallet($ownPallet);
        $logistics->setCaseMultipleRequired($caseMultiple);
        $this->entityManager()->persist($logistics);

        foreach ($capacities as [$type, $quantity, $layer]) {
            $this->capacity($variant, $type, $quantity, $layer);
        }
        $this->entityManager()->flush();

        return $variant;
    }

    /** A variant without any logistics data of the plugin. */
    public function plainVariant(string $code, int $price = 1000, ?TaxonInterface $taxon = null): ProductVariantInterface
    {
        $channel = $this->channel();
        $locale = (string) $channel->getDefaultLocale()?->getCode();
        /** @var FactoryInterface<ProductInterface> $productFactory */
        $productFactory = $this->container->get('sylius.factory.product');
        /** @var FactoryInterface<ProductVariantInterface> $variantFactory */
        $variantFactory = $this->container->get('sylius.factory.product_variant');
        /** @var FactoryInterface<ChannelPricingInterface> $pricingFactory */
        $pricingFactory = $this->container->get('sylius.factory.channel_pricing');

        $product = $productFactory->createNew();
        $product->setCode('P_' . $code . '_' . $this->suffix);
        $product->setCurrentLocale($locale);
        $product->setFallbackLocale($locale);
        $product->setName('Product ' . $code);
        $product->setSlug(strtolower(str_replace('_', '-', 'p-' . $code . '-' . $this->suffix)));
        $product->setEnabled(true);
        $product->addChannel($channel);
        if (null !== $taxon) {
            /** @var FactoryInterface<ProductTaxonInterface> $productTaxonFactory */
            $productTaxonFactory = $this->container->get('sylius.factory.product_taxon');
            $productTaxon = $productTaxonFactory->createNew();
            $productTaxon->setTaxon($taxon);
            $product->addProductTaxon($productTaxon);
        }

        $variant = $variantFactory->createNew();
        $variant->setCode($code . '_' . $this->suffix);
        $variant->setCurrentLocale($locale);
        $variant->setFallbackLocale($locale);
        $variant->setName('Variant ' . $code);
        $variant->setTracked(false);
        $pricing = $pricingFactory->createNew();
        $pricing->setChannelCode($channel->getCode());
        $pricing->setPrice($price);
        $variant->addChannelPricing($pricing);
        $product->addVariant($variant);

        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        return $variant;
    }

    public function capacity(ProductVariantInterface $variant, ContainerTypeInterface $type, int $quantity, ?int $layer = null): VariantContainerCapacityInterface
    {
        /** @var FactoryInterface<VariantContainerCapacityInterface> $factory */
        $factory = $this->container->get('tmonier_container_fill.factory.variant_capacity');
        $capacity = $factory->createNew();
        $capacity->setVariant($variant);
        $capacity->setContainerType($type);
        $capacity->setQuantity($quantity);
        $capacity->setUnitsPerLayer($layer);
        $this->entityManager()->persist($capacity);
        $this->entityManager()->flush();

        return $capacity;
    }

    /** @param list<ContainerTypeInterface> $types */
    public function rule(array $types, string $mode = ContainerRuleInterface::MODE_FULL_ONLY, ?int $threshold = null, ?TaxonInterface $taxon = null, ?ProductInterface $product = null, ?CustomerGroupInterface $group = null): ContainerRuleInterface
    {
        /** @var FactoryInterface<ContainerRuleInterface> $factory */
        $factory = $this->container->get('tmonier_container_fill.factory.container_rule');
        $rule = $factory->createNew();
        $rule->setName('Rule ' . $mode . ' ' . $this->suffix);
        $rule->setChannel($this->channel());
        $rule->setMode($mode);
        $rule->setThreshold($threshold);
        $rule->setScope(null !== $taxon ? ContainerRuleInterface::SCOPE_TAXON : (null !== $product ? ContainerRuleInterface::SCOPE_PRODUCT : ContainerRuleInterface::SCOPE_ALL));
        $rule->setTaxon($taxon);
        $rule->setProduct($product);
        $rule->setCustomerGroup($group);
        foreach ($types as $type) {
            $rule->addContainerType($type);
        }
        $rule->setEnabled(true);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        return $rule;
    }

    /** Disables the rules of other tests and of the demo fixtures in the channel. */
    public function disableOtherRules(): void
    {
        $this->entityManager()->createQuery(sprintf('UPDATE %s r SET r.enabled = false', (string) $this->container->getParameter('tmonier_container_fill.model.container_rule.class')))->execute();
    }

    public function taxon(string $code, ?TaxonInterface $parent = null): TaxonInterface
    {
        /** @var FactoryInterface<TaxonInterface> $factory */
        $factory = $this->container->get('sylius.factory.taxon');
        $locale = (string) $this->channel()->getDefaultLocale()?->getCode();
        $taxon = $factory->createNew();
        $taxon->setCode($code . '_' . $this->suffix);
        $taxon->setCurrentLocale($locale);
        $taxon->setFallbackLocale($locale);
        $taxon->setName('Taxon ' . $code);
        $taxon->setSlug(strtolower($code . '-' . $this->suffix));
        if (null !== $parent) {
            $parent->addChild($taxon);
        }
        $this->entityManager()->persist($taxon);
        $this->entityManager()->flush();

        return $taxon;
    }

    public function group(string $code): CustomerGroupInterface
    {
        /** @var FactoryInterface<CustomerGroupInterface> $factory */
        $factory = $this->container->get('sylius.factory.customer_group');
        $group = $factory->createNew();
        $group->setCode($code . '_' . $this->suffix);
        $group->setName('Group ' . $code);
        $this->entityManager()->persist($group);
        $this->entityManager()->flush();

        return $group;
    }

    /** A customer with an enabled, verified shop account. */
    public function customer(string $name = 'buyer', ?CustomerGroupInterface $group = null): CustomerInterface
    {
        /** @var FactoryInterface<CustomerInterface> $customerFactory */
        $customerFactory = $this->container->get('sylius.factory.customer');
        /** @var FactoryInterface<ShopUserInterface> $userFactory */
        $userFactory = $this->container->get('sylius.factory.shop_user');

        $customer = $customerFactory->createNew();
        $customer->setEmail(strtolower(sprintf('%s-%s@container-fill.test', $name, $this->suffix)));
        $customer->setFirstName('Paula');
        $customer->setLastName('Pallet');
        $customer->setGroup($group);
        $user = $userFactory->createNew();
        $user->setPlainPassword('sylius');
        $user->setEnabled(true);
        $user->setVerifiedAt(new \DateTime());
        $customer->setUser($user);
        $this->entityManager()->persist($customer);
        $this->entityManager()->flush();

        return $customer;
    }

    /**
     * A processed cart of the channel with the given quantities, for a customer (with
     * authorization, so that the cart context finds it) or a guest.
     *
     * @param array<int, array{ProductVariantInterface, int}> $lines
     */
    public function cart(array $lines, ?CustomerInterface $customer = null): OrderInterface
    {
        /** @var FactoryInterface<OrderInterface> $orderFactory */
        $orderFactory = $this->container->get('sylius.factory.order');
        /** @var FactoryInterface<OrderItemInterface> $itemFactory */
        $itemFactory = $this->container->get('sylius.factory.order_item');
        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = $this->container->get('sylius.modifier.order_item_quantity');

        $channel = $this->channel();
        $cart = $orderFactory->createNew();
        $cart->setChannel($channel);
        $cart->setCurrencyCode((string) $channel->getBaseCurrency()?->getCode());
        $cart->setLocaleCode((string) $channel->getDefaultLocale()?->getCode());
        $cart->setTokenValue('cf_' . bin2hex(random_bytes(10)));
        if (null !== $customer) {
            $cart->setCustomerWithAuthorization($customer);
        }
        foreach ($lines as [$variant, $quantity]) {
            $item = $itemFactory->createNew();
            $item->setVariant($variant);
            $quantityModifier->modify($item, $quantity);
            $cart->addItem($item);
        }
        $this->processor()->process($cart);
        $this->entityManager()->persist($cart);
        $this->entityManager()->flush();

        return $cart;
    }

    /**
     * A guest cart ready to complete (addressed, shipping and payment selected) with methods
     * created for it.
     *
     * @param array<int, array{ProductVariantInterface, int}> $lines
     */
    public function checkoutReadyCart(array $lines): OrderInterface
    {
        /** @var FactoryInterface<CustomerInterface> $customerFactory */
        $customerFactory = $this->container->get('sylius.factory.customer');
        $guest = $customerFactory->createNew();
        $guest->setEmail(strtolower('guest-' . $this->suffix . '-' . bin2hex(random_bytes(3)) . '@container-fill.test'));
        $this->entityManager()->persist($guest);

        $cart = $this->cart($lines);
        $cart->setCustomer($guest);
        $country = $this->country();
        foreach (['setShippingAddress', 'setBillingAddress'] as $setter) {
            /** @var FactoryInterface<AddressInterface> $addressFactory */
            $addressFactory = $this->container->get('sylius.factory.address');
            $address = $addressFactory->createNew();
            $address->setFirstName('Paula');
            $address->setLastName('Pallet');
            $address->setStreet('1 Dock Street');
            $address->setCity('Rotterdam');
            $address->setPostcode('3011');
            $address->setCountryCode((string) $country->getCode());
            $cart->{$setter}($address);
        }
        $shippingMethod = $this->shippingMethod($country);
        $paymentMethod = $this->paymentMethod();

        $stateMachine = $this->stateMachine();
        $stateMachine->apply($cart, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        foreach ($cart->getShipments() as $shipment) {
            $shipment->setMethod($shippingMethod);
        }
        $stateMachine->apply($cart, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        foreach ($cart->getPayments() as $payment) {
            $payment->setMethod($paymentMethod);
        }
        $stateMachine->apply($cart, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $this->entityManager()->flush();

        return $cart;
    }

    public function shippingMethod(?CountryInterface $country = null, string $calculator = 'flat_rate', ?array $configuration = null): ShippingMethodInterface
    {
        $country ??= $this->country();
        $channel = $this->channel();
        /** @var FactoryInterface<ZoneInterface> $zoneFactory */
        $zoneFactory = $this->container->get('sylius.factory.zone');
        /** @var FactoryInterface<ZoneMemberInterface> $memberFactory */
        $memberFactory = $this->container->get('sylius.factory.zone_member');
        $zone = $zoneFactory->createNew();
        $zone->setCode('ZONE_' . $this->suffix . bin2hex(random_bytes(2)));
        $zone->setName('Zone ' . $this->suffix);
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $member = $memberFactory->createNew();
        $member->setCode((string) $country->getCode());
        $zone->addMember($member);
        $this->entityManager()->persist($zone);

        /** @var FactoryInterface<ShippingMethodInterface> $methodFactory */
        $methodFactory = $this->container->get('sylius.factory.shipping_method');
        $method = $methodFactory->createNew();
        $method->setCode('SHIP_' . $this->suffix . bin2hex(random_bytes(2)));
        $method->setCurrentLocale((string) $channel->getDefaultLocale()?->getCode());
        $method->setFallbackLocale((string) $channel->getDefaultLocale()?->getCode());
        $method->setName('Freight ' . $this->suffix);
        $method->setZone($zone);
        $method->setCalculator($calculator);
        $method->setConfiguration($configuration ?? [(string) $channel->getCode() => ['amount' => 0]]);
        $method->addChannel($channel);
        $method->setEnabled(true);
        $method->setPosition(-1000);
        $this->entityManager()->persist($method);
        $this->entityManager()->flush();

        return $method;
    }

    public function processor(): OrderProcessorInterface
    {
        $processor = $this->container->get('sylius.order_processing.order_processor');
        Assert::isInstanceOf($processor, OrderProcessorInterface::class);

        return $processor;
    }

    public function stateMachine(): StateMachineInterface
    {
        $stateMachine = $this->container->get('sylius_abstraction.state_machine');
        Assert::isInstanceOf($stateMachine, StateMachineInterface::class);

        return $stateMachine;
    }

    private function containerType(string $code, string $kind, int $slots, int $payloadKg, ?ContainerTypeInterface $nested): ContainerTypeInterface
    {
        /** @var FactoryInterface<ContainerTypeInterface> $factory */
        $factory = $this->container->get('tmonier_container_fill.factory.container_type');
        $locale = (string) $this->channel()->getDefaultLocale()?->getCode();
        $type = $factory->createNew();
        $type->setCode($code . '_' . $this->suffix);
        $type->setCurrentLocale($locale);
        $type->setFallbackLocale($locale);
        $type->setName(ucfirst(strtolower($kind)) . ' ' . $code);
        $type->setKind($kind);
        $type->setSlots($slots);
        $type->setMaxPayload($payloadKg * 1000);
        $type->setNestedType($nested);
        $type->setEnabled(true);
        $this->entityManager()->persist($type);
        $this->entityManager()->flush();

        return $type;
    }

    private function country(): CountryInterface
    {
        $country = $this->entityManager()->getRepository(CountryInterface::class)->findOneBy(['code' => 'NL']);
        if ($country instanceof CountryInterface) {
            $country->setEnabled(true);

            return $country;
        }
        /** @var FactoryInterface<CountryInterface> $factory */
        $factory = $this->container->get('sylius.factory.country');
        $country = $factory->createNew();
        $country->setCode('NL');
        $country->setEnabled(true);
        $this->entityManager()->persist($country);
        $this->entityManager()->flush();

        return $country;
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        $channel = $this->channel();
        /** @var FactoryInterface<PaymentMethodInterface> $factory */
        $factory = $this->container->get('sylius.factory.payment_method');
        $method = $factory->createNew();
        $method->setCode('PAY_' . $this->suffix . bin2hex(random_bytes(2)));
        $method->setCurrentLocale((string) $channel->getDefaultLocale()?->getCode());
        $method->setFallbackLocale((string) $channel->getDefaultLocale()?->getCode());
        $method->setName('Invoice ' . $this->suffix);
        $gatewayConfigFactory = $this->container->get('sylius.factory.gateway_config');
        Assert::isInstanceOf($gatewayConfigFactory, FactoryInterface::class);
        $gatewayConfig = $gatewayConfigFactory->createNew();
        Assert::isInstanceOf($gatewayConfig, \Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface::class);
        $gatewayConfig->setFactoryName('offline');
        $gatewayConfig->setGatewayName('offline_' . strtolower($this->suffix) . bin2hex(random_bytes(2)));
        $method->setGatewayConfig($gatewayConfig);
        $method->addChannel($channel);
        $method->setEnabled(true);
        $this->entityManager()->persist($method);
        $this->entityManager()->flush();

        return $method;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);

        return $entityManager;
    }
}
