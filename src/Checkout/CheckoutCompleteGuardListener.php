<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Checkout;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/**
 * Defence in depth on the "complete" transition of the order checkout workflow (Symfony
 * Workflow adapter only, the Sylius 2 default). Disabled with "workflow_guard: false".
 */
final readonly class CheckoutCompleteGuardListener
{
    public function __construct(
        private CartFillCheckerInterface $checker,
        private FillOverrideChecker $overrideChecker,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface || $event->isBlocked()) {
            return;
        }
        if ($this->checker->check($order)->isSatisfied() || null !== $this->overrideChecker->overridingAdministrator()) {
            return;
        }

        $event->setBlocked(true, 'The container fill rules are not met.');
    }
}
