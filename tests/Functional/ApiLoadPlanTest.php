<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Functional;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;

final class ApiLoadPlanTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheLoadPlanOfAGuestCart(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, 8]]), 50]]);

        $data = $this->loadPlan($cart);

        self::assertResponseIsSuccessful();
        self::assertSame('cart', $data['source']);
        self::assertFalse($data['satisfied']);
        self::assertTrue($data['indicative']);
        self::assertIsArray($data['rules']);
        self::assertCount(1, $data['rules']);
        /** @var array{containerCount: int, containerType: array{code: string}, violations: list<array{code: string}>, suggestion: array{action: string, addUnits: int}|null} $rule */
        $rule = $data['rules'][0];
        self::assertSame(2, $rule['containerCount']);
        self::assertSame($pallet->getCode(), $rule['containerType']['code']);
        self::assertNotSame([], $rule['violations']);
        self::assertNotNull($rule['suggestion']);
        self::assertSame(30, $rule['suggestion']['addUnits']);
        self::assertSame(2, $data['containerCount']);
        self::assertIsArray($data['plan']);
        self::assertCount(1, $data['plan']);
    }

    public function testCustomerCartsAreOnlyReachableByTheirOwner(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $owner = $this->catalog->customer('owner');
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 80]], $owner);

        $this->loadPlan($cart);
        self::assertResponseStatusCodeSame(404);

        $this->loadPlan($cart, $this->catalog->customer('other'));
        self::assertResponseStatusCodeSame(404);

        $data = $this->loadPlan($cart, $owner);
        self::assertResponseIsSuccessful();
        self::assertTrue($data['satisfied']);

        $this->client->request('GET', '/api/v2/shop/orders/unknown-token/load-plan', server: $this->headers());
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheApiCheckoutIsRefusedWhileTheRuleIsNotMet(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 95);
        $cart = $this->catalog->checkoutReadyCart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);

        $this->complete($cart);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('fill', (string) $this->client->getResponse()->getContent());
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(OrderInterface::class, $cart->getId());
        self::assertInstanceOf(OrderInterface::class, $reloaded);
        self::assertSame(OrderInterface::STATE_CART, $reloaded->getState());
    }

    public function testTheApiCheckoutRefusesQuantitiesThatAreNotCaseMultiples(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 10);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], 10000, 4, caseMultiple: true);
        $cart = $this->catalog->checkoutReadyCart([[$variant, 30]]);

        $data = $this->loadPlan($cart);
        self::assertIsArray($data['lineViolations']);
        self::assertCount(1, $data['lineViolations']);

        $this->complete($cart);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheApiCartRefusesPartialCases(): void
    {
        $pallet = $this->catalog->pallet();
        $variant = $this->catalog->variant('WATER', [[$pallet, 480, null]], 9300, 6, caseMultiple: true);
        $cart = $this->catalog->cart([]);
        $items = sprintf('/api/v2/shop/orders/%s/items', $cart->getTokenValue());
        $iri = '/api/v2/shop/product-variants/' . $variant->getCode();

        $this->client->request('POST', $items, server: $this->headers(), content: json_encode(['productVariant' => $iri, 'quantity' => 4], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('multiple of 6', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', $items, server: $this->headers(), content: json_encode(['productVariant' => $iri, 'quantity' => 12], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $data = $this->json();
        self::assertIsArray($data['items']);
        self::assertIsArray($data['items'][0]);
        $itemId = $data['items'][0]['id'];
        self::assertIsScalar($itemId);

        $this->client->request('PATCH', sprintf('%s/%s', $items, (string) $itemId), server: ['CONTENT_TYPE' => 'application/merge-patch+json'] + $this->headers(), content: json_encode(['quantity' => 7], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('PATCH', sprintf('%s/%s', $items, (string) $itemId), server: ['CONTENT_TYPE' => 'application/merge-patch+json'] + $this->headers(), content: json_encode(['quantity' => 18], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }

    public function testACompletedOrderExposesItsSnapshot(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 95);
        $cart = $this->catalog->checkoutReadyCart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 40]]);

        $this->complete($cart);
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $order = $this->entityManager()->find(OrderInterface::class, $cart->getId());
        self::assertInstanceOf(OrderInterface::class, $order);
        self::assertSame(OrderInterface::STATE_NEW, $order->getState());
        $snapshot = $this->entityManager()->getRepository(OrderLoadPlanSnapshotInterface::class)->findOneBy(['order' => $order]);
        self::assertInstanceOf(OrderLoadPlanSnapshotInterface::class, $snapshot);

        $data = $this->loadPlan($order);
        self::assertResponseIsSuccessful();
        self::assertSame('snapshot', $data['source']);
        self::assertTrue($data['satisfied']);
        self::assertIsArray($data['rules']);
        self::assertCount(1, $data['rules']);
        self::assertNull($data['overriddenBy']);
    }

    public function testAPlacedOrderWithoutPlanSaysSo(): void
    {
        $cart = $this->catalog->checkoutReadyCart([[$this->catalog->plainVariant('A'), 3]]);

        $this->complete($cart);
        self::assertResponseIsSuccessful();

        $data = $this->loadPlan($cart);
        self::assertResponseIsSuccessful();
        self::assertContains($data['source'], ['none', 'snapshot']);
        self::assertTrue($data['satisfied']);
    }

    public function testTheOperationIsDocumented(): void
    {
        $this->client->request('GET', '/api/v2/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

        self::assertResponseIsSuccessful();
        $docs = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($docs);
        self::assertIsArray($docs['paths'] ?? null);
        self::assertArrayHasKey('/api/v2/shop/orders/{tokenValue}/load-plan', $docs['paths']);
    }

    /** @return array<string, mixed> */
    private function loadPlan(OrderInterface $order, ?CustomerInterface $as = null): array
    {
        $this->client->request('GET', sprintf('/api/v2/shop/orders/%s/load-plan', $order->getTokenValue()), server: $this->headers($as));

        return $this->json();
    }

    private function complete(OrderInterface $cart): void
    {
        $this->client->request(
            'PATCH',
            sprintf('/api/v2/shop/orders/%s/complete', $cart->getTokenValue()),
            server: ['CONTENT_TYPE' => 'application/merge-patch+json'] + $this->headers(),
            content: json_encode(['notes' => null], \JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        if (!\is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $typed */
        $typed = $data;

        return $typed;
    }

    /** @return array<string, string> */
    private function headers(?CustomerInterface $as = null): array
    {
        $headers = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
        if (null !== $as) {
            $user = $as->getUser();
            self::assertInstanceOf(ShopUserInterface::class, $user);
            $manager = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
            self::assertInstanceOf(JWTTokenManagerInterface::class, $manager);
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $manager->create($user);
        }

        return $headers;
    }
}
