<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

final class ShopCheckoutTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheCartShowsTheGaugeAndDisablesTheCheckout(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, 8]]), 50]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/cart/'));

        self::assertResponseIsSuccessful();
        $gauge = $crawler->filter('[data-test-tmonier-container-fill-gauge]');
        self::assertCount(1, $gauge);
        self::assertCount(2, $gauge->filter('[data-test-tmonier-container-fill-container]'));
        $meter = $gauge->filter('[role="meter"]')->last();
        self::assertSame('25', $meter->attr('aria-valuenow'));
        self::assertStringContainsString('25 %', (string) $meter->attr('aria-valuetext'));
        self::assertStringContainsString('places', (string) $meter->attr('aria-valuetext'));
        self::assertStringContainsString('Add 30', $gauge->filter('[data-test-tmonier-container-fill-complete="0"]')->text());
        self::assertCount(1, $gauge->filter('[data-test-tmonier-container-fill-round-layer]'));
        self::assertNotNull($crawler->filter('[data-test-cart-checkout-button]')->attr('disabled'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-container-fill-checkout-reason]'));
    }

    public function testAFullCartKeepsTheCheckoutEnabled(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 80]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/cart/'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('The fill rules are met.', $crawler->filter('[data-test-tmonier-container-fill-status]')->text());
        self::assertNull($crawler->filter('[data-test-cart-checkout-button]')->attr('disabled'));
    }

    public function testNoGaugeWithoutApplicableRule(): void
    {
        $pallet = $this->catalog->pallet();
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 5]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/cart/'));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-container-fill-gauge]'));
    }

    public function testTheCheckoutOfASignedInCustomerIsSentBackToTheCart(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 95);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]], $customer);
        $this->loginShopUser($customer);

        foreach (['/checkout/address', '/checkout/select-shipping', '/checkout/complete'] as $path) {
            $this->client->request('GET', $this->url($path));
            self::assertResponseRedirects($this->url('/cart/'), null, $path);
        }

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('fill rules', $crawler->filter('[data-test-flash-messages]')->text());
    }

    public function testTheCheckoutOpensOnceTheRuleIsMet(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 95);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 39]], $customer);
        $this->loginShopUser($customer);

        $this->client->request('GET', $this->url('/checkout/address'));

        self::assertResponseIsSuccessful();
    }

    public function testThePreviewOnTheProductPage(): void
    {
        $pallet = $this->catalog->pallet();
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], 10000, 4);

        $crawler = $this->client->request('GET', $this->url('/products/' . $variant->getProduct()?->getSlug()));

        self::assertResponseIsSuccessful();
        $preview = $crawler->filter('[data-test-tmonier-container-fill-preview]');
        self::assertCount(1, $preview);
        self::assertStringContainsString('Sold by case of 4', $preview->text());
        self::assertStringContainsString('2.5 %', $preview->text());
    }

    public function testThePreviewShowsTheCurrentFillOfTheCart(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 10);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], 10000, 4);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$variant, 50]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/products/' . $variant->getProduct()?->getSlug()));

        self::assertResponseIsSuccessful();
        $line = $crawler->filter('[data-test-tmonier-container-fill-preview-cart]');
        self::assertCount(1, $line);
        self::assertStringContainsString('2 x ' . $pallet->getName() . ', the last one filled to 25 %', $line->text());
    }

    public function testACombinedRuleShowsTheContainersItsViolationRefersTo(): void
    {
        $pallet = $this->catalog->pallet();
        $taxon = $this->catalog->taxon('PALLETS');
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_FULL_ONLY, null, $taxon);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 50);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]], taxon: $taxon), 40], [$this->catalog->variant('B', [[$pallet, 40, null]]), 10]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/cart/'));

        self::assertResponseIsSuccessful();
        // Plan: one pallet per rule; the "all" rule is checked on A + B (2 pallets) and says "container 2".
        self::assertCount(2, $crawler->filter('[data-test-tmonier-container-fill-plan]'));
        $rule = $crawler->filter('[data-test-tmonier-container-fill-rule="1"]');
        self::assertStringContainsString('Container 2 is filled to 25 %', $rule->text());
        $containers = $rule->filter('[data-test-tmonier-container-fill-rule-containers]');
        self::assertCount(1, $containers);
        self::assertStringContainsString('2 of 2: 25 %', $containers->text());
        self::assertCount(0, $crawler->filter('[data-test-tmonier-container-fill-rule="0"] [data-test-tmonier-container-fill-rule-containers]'));
    }

    public function testIdenticalFullContainersAreGrouped(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]], 1000), 130]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/cart/'));

        self::assertResponseIsSuccessful();
        $group = $crawler->filter('[data-test-tmonier-container-fill-full-group="3"]');
        self::assertCount(1, $group);
        self::assertStringContainsString('3 of 4', $group->text());
        self::assertCount(1, $crawler->filter('[data-test-tmonier-container-fill-container]'));
        self::assertStringContainsString('4 of 4: 25 %', $crawler->filter('[data-test-tmonier-container-fill-container]')->text());
    }

    public function testThePreviewCountsWhatFitsByPlacesAndPayload(): void
    {
        // 40 per pallet by places, but 30 kg units on a 1000 kg pallet: 33 fit.
        $pallet = $this->catalog->pallet();
        $variant = $this->catalog->variant('HEAVY', [[$pallet, 40, null]], 30000);
        $product = $variant->getProduct();
        self::assertNotNull($product);
        $extension = self::getContainer()->get('tmonier_container_fill.twig.extension');
        self::assertInstanceOf(\Tmonier\SyliusContainerFillPlugin\Twig\ContainerFillExtension::class, $extension);

        $rows = array_values(array_filter($extension->preview($variant, 66), static fn (array $row): bool => $row['name'] === $pallet->getName()));

        self::assertCount(1, $rows);
        self::assertSame(33, $rows[0]['capacity']);
        self::assertSame(200.0, $rows[0]['percent']);
        self::assertSame(2.0, $rows[0]['count']);

        $crawler = $this->client->request('GET', $this->url('/products/' . $product->getSlug()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1 = 3 % of a ' . $pallet->getName() . ' (33 fill one)', $crawler->filter('[data-test-tmonier-container-fill-preview]')->text());
    }

    public function testTheGaugeShowsTheStackOfLayers(): void
    {
        $pallet = $this->catalog->layerPallet();
        $this->catalog->rule([$pallet]);
        $water = $this->catalog->variant('WATER', [[$pallet, 480, 60]], 1550);
        $this->catalog->layers($water, 200);
        $customer = $this->catalog->customer();
        $this->catalog->cart([[$water, 300]], $customer);
        $this->loginShopUser($customer);

        $crawler = $this->client->request('GET', $this->url('/cart/'));

        self::assertResponseIsSuccessful();
        $stack = $crawler->filter('[data-test-tmonier-container-fill-stack="1"]');
        self::assertCount(1, $stack);
        self::assertStringContainsString('5 layers', $stack->text());
        self::assertStringContainsString('5 x layer of Product WATER (60/60)', $stack->text());
        self::assertStringContainsString('Room for 3 more layers of Product WATER', $stack->filter('[data-test-tmonier-container-fill-remaining-layers]')->text());
        self::assertCount(5, $stack->filter('svg rect[fill="#206bc4"]'));
        // Stacked from the bottom: one layer per height.
        $ys = [];
        foreach ($stack->filter('svg rect[fill="#206bc4"]') as $node) {
            $ys[] = $node instanceof \DOMElement ? (string) round((float) $node->getAttribute('y')) : '';
        }
        // 20 px per 200 mm layer, the empty room above cropped to 48 px, 10 px of pallet.
        self::assertSame(['128', '108', '88', '68', '48'], $ys);
        $svg = $stack->filter('svg');
        self::assertSame('true', $svg->attr('aria-hidden'));
        self::assertSame('0 0 160 158', $svg->attr('viewBox'));
        self::assertSame(['160', '158'], [$svg->attr('width'), $svg->attr('height')]);
        self::assertCount(1, $svg->filter('path'), 'Cropped room: break mark.');
        self::assertStringContainsString('limited by stack height', $crawler->filter('[data-test-tmonier-container-fill-container="1"]')->text());
        // Rounding up to the layer: 300 is 5 full layers, nothing to round.
        self::assertCount(0, $crawler->filter('[data-test-tmonier-container-fill-round-layer]'));
    }

    public function testThePreviewSpeaksInLayers(): void
    {
        $pallet = $this->catalog->layerPallet();
        $water = $this->catalog->variant('WATER', [[$pallet, 480, 60]], 1550);
        $this->catalog->layers($water, 200);
        $extension = self::getContainer()->get('tmonier_container_fill.twig.extension');
        self::assertInstanceOf(\Tmonier\SyliusContainerFillPlugin\Twig\ContainerFillExtension::class, $extension);

        $rows = array_values(array_filter($extension->preview($water, 90), static fn (array $row): bool => $row['name'] === $pallet->getName()));

        self::assertCount(1, $rows);
        self::assertSame(1.5, $rows[0]['layers']);
        self::assertSame(18.8, $rows[0]['percent']);

        $crawler = $this->client->request('GET', $this->url('/products/' . $water->getProduct()?->getSlug()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1 = 0.02 layers', $crawler->filter('[data-test-tmonier-container-fill-preview]')->text());
    }
}
