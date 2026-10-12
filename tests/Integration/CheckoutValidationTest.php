<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Sylius\Bundle\ApiBundle\Command\Checkout\CompleteOrder;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Checkout\CheckoutEntrySubscriber;
use Tmonier\SyliusContainerFillPlugin\Entity\OrderLoadPlanSnapshotInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\OrderLoadPlanSnapshotRepositoryInterface;

final class CheckoutValidationTest extends KernelTestCase
{
    use ContainerFillTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testAPartialCartIsValidInTheCartGroupButNotAtCheckoutComplete(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);

        self::assertCount(0, $this->validator()->validate($cart, null, ['sylius']));
        $violations = $this->validator()->validate($cart, null, ['sylius_checkout_complete']);
        self::assertCount(1, $violations);
        self::assertSame('not_full', $violations[0]->getCode());
        self::assertStringContainsString('75', (string) $violations[0]->getMessage());
    }

    public function testTheApiCompleteCommandIsValidatedToo(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);

        $violations = $this->validator()->validate(new CompleteOrder((string) $cart->getTokenValue()), null, ['sylius_checkout_complete']);
        $codes = array_map(static fn ($violation): ?string => $violation->getCode(), iterator_to_array($violations));
        self::assertContains('not_full', $codes);
    }

    public function testCaseMultiplesAreCheckedWhenTheCartIsSaved(): void
    {
        $pallet = $this->catalog->pallet();
        $cart = $this->catalog->cart([[$this->catalog->variant('WATER', [[$pallet, 480, null]], 9300, 6, false, true), 14]]);

        $violations = $this->validator()->validate($cart->getItems()->first(), null, ['sylius']);
        self::assertCount(1, $violations);
        self::assertSame('quantity', $violations[0]->getPropertyPath());
        self::assertStringContainsString('6', (string) $violations[0]->getMessage());
    }

    public function testPlacedOrdersAreNotRevalidated(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);
        $cart->setState('new');

        self::assertCount(0, $this->validator()->validate($cart, null, ['sylius_checkout_complete']));
    }

    public function testTheWorkflowGuardBlocksCompleteAndTheSnapshotIsWritten(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]]);

        $partial = $this->catalog->checkoutReadyCart([[$variant, 30]]);
        self::assertFalse($this->catalog->stateMachine()->can($partial, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE));

        $full = $this->catalog->checkoutReadyCart([[$variant, 40]]);
        self::assertTrue($this->catalog->stateMachine()->can($full, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE));
        $this->catalog->stateMachine()->apply($full, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->entityManager()->flush();

        $snapshot = $this->snapshots()->findOneByOrder($full);
        self::assertInstanceOf(OrderLoadPlanSnapshotInterface::class, $snapshot);
        self::assertTrue($snapshot->isSatisfied());
        self::assertNull($snapshot->getOverriddenBy());
        $plan = $snapshot->getPlan();
        self::assertSame(1, $plan['rules'][0]['containerCount'] ?? null);
    }

    public function testAnAdministratorWithTheOverrideRoleCompletesAndIsRecorded(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $cart = $this->catalog->checkoutReadyCart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);

        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy([]);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->login($admin);
        self::assertCount(1, $this->validator()->validate($cart, null, ['sylius_checkout_complete']), 'Without the role.');

        $admin->addRole('ROLE_TMONIER_CONTAINER_FILL_OVERRIDE');
        $this->login($admin);
        self::assertCount(0, $this->validator()->validate($cart, null, ['sylius_checkout_complete']));
        $this->catalog->stateMachine()->apply($cart, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->entityManager()->flush();

        $snapshot = $this->snapshots()->findOneByOrder($cart);
        self::assertNotNull($snapshot);
        self::assertFalse($snapshot->isSatisfied());
        self::assertSame($admin->getUserIdentifier(), $snapshot->getOverriddenBy());
    }

    public function testTheCheckoutEntryListenerRunsBetweenTheFirewallAndTheCheckoutResolver(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $priorities = [];
        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $listener) {
            $object = \is_array($listener) ? $listener[0] : $listener;
            if (\is_object($object)) {
                $priorities[$object::class] = $dispatcher->getListenerPriority(KernelEvents::REQUEST, $listener);
            }
        }

        $ours = $priorities[CheckoutEntrySubscriber::class] ?? null;
        self::assertSame(CheckoutEntrySubscriber::PRIORITY, $ours);
        self::assertGreaterThanOrEqual(1, $ours);
        self::assertLessThanOrEqual(7, $ours);
        self::assertSame(0, $priorities['Sylius\Bundle\CoreBundle\Checkout\CheckoutResolver'] ?? null);
    }

    private function login(AdminUserInterface $admin): void
    {
        $tokenStorage = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'admin', $admin->getRoles()));
    }

    private function validator(): ValidatorInterface
    {
        $validator = self::getContainer()->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator;
    }

    private function snapshots(): OrderLoadPlanSnapshotRepositoryInterface
    {
        $repository = self::getContainer()->get('tmonier_container_fill.repository.order_load_plan');
        self::assertInstanceOf(OrderLoadPlanSnapshotRepositoryInterface::class, $repository);

        return $repository;
    }
}
