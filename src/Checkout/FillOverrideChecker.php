<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Checkout;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * An administrator granted the override role (configuration "override_role") may complete an
 * order that does not meet the fill rules (admin order creation, admin API). Shop customers
 * never can.
 */
final readonly class FillOverrideChecker
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AuthorizationCheckerInterface $authorizationChecker,
        private ?string $overrideRole,
    ) {
    }

    /** User identifier of the administrator overriding the rules, null when nobody may. */
    public function overridingAdministrator(): ?string
    {
        if (null === $this->overrideRole || '' === $this->overrideRole) {
            return null;
        }
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface || !$this->authorizationChecker->isGranted($this->overrideRole)) {
            return null;
        }

        return $user->getUserIdentifier();
    }
}
