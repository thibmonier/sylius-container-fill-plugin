<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Functional;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\EntityId;

final class AdminTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheAdministrationIsReservedToAdministrators(): void
    {
        $this->client->request('GET', '/admin/container-types/');
        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->loginShopUser($this->catalog->customer());
        $this->client->request('GET', '/admin/container-rules/');
        self::assertResponseRedirects();
    }

    public function testTheMenuAndTheGrids(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 80);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/container-types/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $pallet->getName(), $crawler->filter('table')->text());
        self::assertGreaterThan(0, $crawler->filter('a[href$="/admin/container-rules/"]')->count());

        $crawler = $this->client->request('GET', '/admin/container-rules/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Rule min_fill_percent ' . $this->catalog->suffix, $crawler->filter('table')->text());
    }

    public function testTheMerchantCreatesAndEditsAContainerType(): void
    {
        $this->loginAdmin();
        $code = 'HALF_' . $this->catalog->suffix;

        $crawler = $this->client->request('GET', '/admin/container-types/new');
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Create')->form()->getPhpValues();
        $fields = $values['tmonier_container_fill_container_type'] ?? null;
        self::assertIsArray($fields);
        $fields['code'] = $code;
        $fields['kind'] = ContainerTypeInterface::KIND_PALLET;
        $fields['maxPayload'] = '500.5';
        $fields['slots'] = '1';
        $fields['innerLength'] = '800';
        $fields['innerWidth'] = '600';
        $fields['maxLoadHeight'] = '1200';
        $fields['volumeEfficiency'] = '85';
        $fields['enabled'] = '1';
        self::assertIsArray($fields['translations']);
        foreach (array_keys($fields['translations']) as $locale) {
            $fields['translations'][$locale] = ['name' => 'Half pallet ' . $this->catalog->suffix];
        }
        $values['tmonier_container_fill_container_type'] = $fields;
        $this->client->request('POST', '/admin/container-types/new', $values);
        self::assertResponseRedirects();

        $type = $this->entityManager()->getRepository(ContainerTypeInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ContainerTypeInterface::class, $type);
        self::assertSame(500500, $type->getMaxPayload());
        self::assertSame(800, $type->getInnerLength());

        // Edit: the payload is shown back in kilograms; a type cannot be nested in itself.
        $crawler = $this->client->request('GET', sprintf('/admin/container-types/%d/edit', (int) $type->getId()));
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Update')->form()->getPhpValues();
        $fields = $values['tmonier_container_fill_container_type'] ?? null;
        self::assertIsArray($fields);
        self::assertIsNumeric($fields['maxPayload']);
        self::assertEqualsWithDelta(500.5, (float) $fields['maxPayload'], 0.0001);
        $fields['maxPayload'] = '';
        $values['tmonier_container_fill_container_type'] = $fields;
        $this->client->request('POST', sprintf('/admin/container-types/%d/edit', (int) $type->getId()), $values);
        self::assertResponseStatusCodeSame(422, 'The payload is mandatory.');
    }

    public function testTheMerchantCreatesARuleAndInvalidRulesAreRefused(): void
    {
        $pallet = $this->catalog->pallet();
        $taxon = $this->catalog->taxon('PALLETS');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/container-rules/new');
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Create')->form()->getPhpValues();
        $fields = $values['tmonier_container_fill_container_rule'] ?? null;
        self::assertIsArray($fields);
        $fields['name'] = 'Full pallets ' . $this->catalog->suffix;
        $fields['channel'] = (string) $this->catalog->channel()->getCode();
        $fields['scope'] = ContainerRuleInterface::SCOPE_TAXON;
        // Autocomplete fields are filled by JavaScript in the browser.
        $fields['taxon'] = EntityId::string($taxon->getId());
        $fields['containerTypes'] = [(string) $pallet->getId()];
        $fields['mode'] = ContainerRuleInterface::MODE_MIN_FILL_PERCENT;
        $fields['threshold'] = '';
        $fields['enabled'] = '1';
        $values['tmonier_container_fill_container_rule'] = $fields;

        $this->client->request('POST', '/admin/container-rules/new', $values);
        self::assertResponseStatusCodeSame(422, 'A minimum fill needs a percentage.');

        $incomplete = $values;
        unset($incomplete['tmonier_container_fill_container_rule']['scope'], $incomplete['tmonier_container_fill_container_rule']['mode']);
        $this->client->request('POST', '/admin/container-rules/new', $incomplete);
        self::assertResponseStatusCodeSame(422, 'No scope nor mode: a form error, not a server error.');

        $values['tmonier_container_fill_container_rule']['threshold'] = '90';
        $this->client->request('POST', '/admin/container-rules/new', $values);
        self::assertResponseRedirects();

        $rule = $this->entityManager()->getRepository(ContainerRuleInterface::class)->findOneBy(['name' => 'Full pallets ' . $this->catalog->suffix]);
        self::assertInstanceOf(ContainerRuleInterface::class, $rule);
        self::assertSame($taxon->getId(), $rule->getTaxon()?->getId());
        self::assertSame(90, $rule->getThreshold());
        self::assertCount(1, $rule->getContainerTypes());
    }

    public function testTheLogisticsTabOfTheVariantForm(): void
    {
        $pallet = $this->catalog->pallet();
        $variant = $this->catalog->plainVariant('A');
        $product = $variant->getProduct();
        self::assertNotNull($product);
        $this->loginAdmin();
        $url = sprintf('/admin/products/%d/variants/%d/edit', EntityId::int($product->getId()), EntityId::int($variant->getId()));

        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-container-fill-tab]'));
        $row = sprintf('t%d', (int) $pallet->getId());
        self::assertCount(1, $crawler->filter(sprintf('[data-test-tmonier-container-fill-capacity="%s"]', $row)));

        $values = $crawler->selectButton('Update')->form()->getPhpValues();
        $formName = array_key_first(array_filter($values, static fn (mixed $value): bool => \is_array($value) && \array_key_exists('tmonierContainerFill', $value)));
        self::assertIsString($formName);
        self::assertIsArray($values[$formName]);
        $values[$formName]['tmonierContainerFill'] = [
            'unitsPerCase' => '6',
            'caseWeight' => '12.5',
            'caseMultipleRequired' => '1',
            'ownPallet' => '1',
            'capacities' => [$row => ['quantity' => '48', 'unitsPerLayer' => '12']],
        ];
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects();

        $this->entityManager()->clear();
        $logistics = $this->entityManager()->getRepository(ProductVariantLogisticsInterface::class)->findOneBy(['variant' => $variant->getId()]);
        self::assertInstanceOf(ProductVariantLogisticsInterface::class, $logistics);
        self::assertSame(6, $logistics->getUnitsPerCase());
        self::assertSame(12500, $logistics->getCaseWeight());
        self::assertTrue($logistics->isCaseMultipleRequired());
        self::assertTrue($logistics->isOwnPallet());
        $capacity = $this->entityManager()->getRepository(VariantContainerCapacityInterface::class)->findOneBy(['variant' => $variant->getId(), 'containerType' => $pallet->getId()]);
        self::assertInstanceOf(VariantContainerCapacityInterface::class, $capacity);
        self::assertSame(48, $capacity->getQuantity());
        self::assertSame(12, $capacity->getUnitsPerLayer());

        // Emptying the capacity removes it.
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Update')->form()->getPhpValues();
        self::assertIsArray($values[$formName]);
        self::assertIsArray($values[$formName]['tmonierContainerFill']);
        $values[$formName]['tmonierContainerFill']['capacities'] = [$row => ['quantity' => '', 'unitsPerLayer' => '']];
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects();
        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->getRepository(VariantContainerCapacityInterface::class)->findOneBy(['variant' => $variant->getId(), 'containerType' => $pallet->getId()]));
    }

    public function testTheOrderPageShowsTheRecordedLoadPlan(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 50);
        $order = $this->catalog->checkoutReadyCart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);
        $this->catalog->stateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->entityManager()->flush();
        self::assertSame(OrderInterface::STATE_NEW, $order->getState());
        $this->loginAdmin();

        $crawler = $this->client->request('GET', sprintf('/admin/orders/%d', EntityId::int($order->getId())));

        self::assertResponseIsSuccessful();
        $card = $crawler->filter('[data-test-tmonier-container-fill-load-plan]');
        self::assertCount(1, $card);
        self::assertStringContainsString('75 %', $card->text());
        self::assertStringContainsString((string) $pallet->getName(), $card->text());
        self::assertCount(0, $card->filter('[data-test-tmonier-container-fill-override]'));
    }

    public function testAnOrderWithoutPlan(): void
    {
        $order = $this->catalog->checkoutReadyCart([[$this->catalog->plainVariant('A'), 2]]);
        $this->catalog->stateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->entityManager()->flush();
        $this->loginAdmin();

        $crawler = $this->client->request('GET', sprintf('/admin/orders/%d', EntityId::int($order->getId())));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-container-fill-load-plan]'));
    }
}
