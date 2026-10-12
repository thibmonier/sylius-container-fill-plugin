<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;

/**
 * Demo data: three pallet-sold products in a "Pallet goods" taxon (capacities for the EUR
 * pallet, the standard semi-trailer derives from it), a "full pallets only" rule on that taxon
 * in every channel, and a verified buyer. Idempotent; needs the container type presets.
 */
final class ContainerFillDemoFixture extends AbstractFixture
{
    public const TAXON_CODE = 'TMONIER_CF_PALLET_GOODS';

    public const BUYER_EMAIL = 'pallet@example.com';

    /** code, name, slug, price (cents), units per case, case weight (g), own pallet, case multiple, EUR pallet capacity (units), units per layer */
    private const PRODUCTS = [
        ['TMONIER_CF_WATER', 'Spring water 1.5 L (case of 6)', 'spring-water-case', 390, 6, 9300, false, true, 480, 60],
        ['TMONIER_CF_CEMENT', 'Cement bag 25 kg', 'cement-bag-25-kg', 790, 1, 25000, true, false, 40, 5],
        ['TMONIER_CF_TILES', 'Ceramic floor tiles (box of 1.44 m²)', 'ceramic-floor-tiles-box', 3400, 1, 22000, false, false, 48, 8],
    ];

    /**
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param FactoryInterface<ProductInterface> $productFactory
     * @param FactoryInterface<ProductVariantInterface> $variantFactory
     * @param FactoryInterface<ChannelPricingInterface> $channelPricingFactory
     * @param RepositoryInterface<ProductInterface> $productRepository
     * @param FactoryInterface<TaxonInterface> $taxonFactory
     * @param RepositoryInterface<TaxonInterface> $taxonRepository
     * @param FactoryInterface<ProductTaxonInterface> $productTaxonFactory
     * @param FactoryInterface<ProductVariantLogisticsInterface> $logisticsFactory
     * @param FactoryInterface<VariantContainerCapacityInterface> $capacityFactory
     * @param FactoryInterface<ContainerRuleInterface> $ruleFactory
     * @param FactoryInterface<ShopUserInterface> $shopUserFactory
     * @param FactoryInterface<CustomerInterface> $customerFactory
     * @param RepositoryInterface<ShopUserInterface> $shopUserRepository
     */
    public function __construct(
        private readonly ObjectManager $manager,
        private readonly RepositoryInterface $channelRepository,
        private readonly FactoryInterface $productFactory,
        private readonly FactoryInterface $variantFactory,
        private readonly FactoryInterface $channelPricingFactory,
        private readonly RepositoryInterface $productRepository,
        private readonly FactoryInterface $taxonFactory,
        private readonly RepositoryInterface $taxonRepository,
        private readonly FactoryInterface $productTaxonFactory,
        private readonly ContainerTypeRepositoryInterface $containerTypeRepository,
        private readonly FactoryInterface $logisticsFactory,
        private readonly FactoryInterface $capacityFactory,
        private readonly FactoryInterface $ruleFactory,
        private readonly FactoryInterface $shopUserFactory,
        private readonly FactoryInterface $customerFactory,
        private readonly RepositoryInterface $shopUserRepository,
    ) {
    }

    public function getName(): string
    {
        return 'tmonier_container_fill_demo';
    }

    public function load(array $options): void
    {
        $this->createBuyer();

        if (null !== $this->productRepository->findOneBy(['code' => self::PRODUCTS[0][0]])) {
            $this->manager->flush();

            return;
        }

        $pallet = $this->containerTypeRepository->findOneByCode('EUR_PALLET');
        $semi = $this->containerTypeRepository->findOneByCode('SEMI_13_6');
        if (null === $pallet || null === $semi) {
            throw new \RuntimeException('Load the "tmonier_container_fill_container_types" fixture first.');
        }

        /** @var list<ChannelInterface> $channels */
        $channels = $this->channelRepository->findAll();
        $locales = [];
        foreach ($channels as $channel) {
            foreach ($channel->getLocales() as $locale) {
                /** @var LocaleInterface $locale */
                $locales[(string) $locale->getCode()] = true;
            }
        }
        $locales = array_keys($locales) ?: ['en_US'];

        $taxon = $this->taxonFactory->createNew();
        $taxon->setCode(self::TAXON_CODE);
        foreach ($locales as $locale) {
            $taxon->setCurrentLocale($locale);
            $taxon->setFallbackLocale($locale);
            $taxon->setName('Pallet goods');
            $taxon->setSlug('pallet-goods');
        }
        $this->manager->persist($taxon);

        foreach (self::PRODUCTS as [$code, $name, $slug, $price, $unitsPerCase, $caseWeight, $ownPallet, $caseMultiple, $capacity, $layer]) {
            $product = $this->productFactory->createNew();
            $product->setCode($code);
            $product->setEnabled(true);
            foreach ($locales as $locale) {
                $product->setCurrentLocale($locale);
                $product->setFallbackLocale($locale);
                $product->setName($name);
                $product->setSlug($slug);
                $product->setShortDescription('Demo product of the Pallet & Truckload Builder: sold by full EUR pallets.');
            }
            foreach ($channels as $channel) {
                $product->addChannel($channel);
            }
            $product->setMainTaxon($taxon);
            $productTaxon = $this->productTaxonFactory->createNew();
            $productTaxon->setTaxon($taxon);
            $product->addProductTaxon($productTaxon);

            $variant = $this->variantFactory->createNew();
            $variant->setCode($code);
            $variant->setTracked(false);
            foreach ($locales as $locale) {
                $variant->setCurrentLocale($locale);
                $variant->setFallbackLocale($locale);
                $variant->setName($name);
            }
            foreach ($channels as $channel) {
                $pricing = $this->channelPricingFactory->createNew();
                $pricing->setChannelCode($channel->getCode());
                $pricing->setPrice($price);
                $variant->addChannelPricing($pricing);
            }
            $product->addVariant($variant);
            $this->manager->persist($product);

            $logistics = $this->logisticsFactory->createNew();
            $logistics->setVariant($variant);
            $logistics->setUnitsPerCase($unitsPerCase);
            $logistics->setCaseWeight($caseWeight);
            $logistics->setOwnPallet($ownPallet);
            $logistics->setCaseMultipleRequired($caseMultiple);
            $this->manager->persist($logistics);

            $palletCapacity = $this->capacityFactory->createNew();
            $palletCapacity->setVariant($variant);
            $palletCapacity->setContainerType($pallet);
            $palletCapacity->setQuantity($capacity);
            $palletCapacity->setUnitsPerLayer($layer);
            $this->manager->persist($palletCapacity);
        }

        foreach ($channels as $channel) {
            $rule = $this->ruleFactory->createNew();
            $rule->setName('Pallet goods: full pallets or a full truck');
            $rule->setChannel($channel);
            $rule->setScope(ContainerRuleInterface::SCOPE_TAXON);
            $rule->setTaxon($taxon);
            $rule->setMode(ContainerRuleInterface::MODE_FULL_ONLY);
            $rule->addContainerType($pallet);
            $rule->addContainerType($semi);
            $rule->setEnabled(true);
            $this->manager->persist($rule);
        }

        $this->manager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
    }

    /** The demo channel requires verified accounts: the buyer is created verified. */
    private function createBuyer(): void
    {
        $user = $this->shopUserRepository->findOneBy(['username' => self::BUYER_EMAIL]);
        if ($user instanceof ShopUserInterface) {
            $user->setVerifiedAt($user->getVerifiedAt() ?? new \DateTime());

            return;
        }

        $customer = $this->customerFactory->createNew();
        $customer->setEmail(self::BUYER_EMAIL);
        $customer->setFirstName('Paula');
        $customer->setLastName('Pallet');

        $user = $this->shopUserFactory->createNew();
        $user->setCustomer($customer);
        $user->setUsername(self::BUYER_EMAIL);
        $user->setPlainPassword('sylius');
        $user->setEnabled(true);
        $user->setVerifiedAt(new \DateTime());
        $this->manager->persist($user);
    }
}
