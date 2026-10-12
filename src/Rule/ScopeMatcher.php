<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\EntityId;

/** Products in the scope of a rule (taxon scope includes the descendants of the taxon). */
final class ScopeMatcher
{
    public function matches(ContainerRuleInterface $rule, ProductInterface $product): bool
    {
        return match ($rule->getScope()) {
            ContainerRuleInterface::SCOPE_ALL => true,
            ContainerRuleInterface::SCOPE_PRODUCT => null !== $rule->getProduct() && $rule->getProduct()->getId() === $product->getId(),
            ContainerRuleInterface::SCOPE_TAXON => null !== $rule->getTaxon() && $this->inTaxon($product, $rule->getTaxon()),
            default => false,
        };
    }

    /**
     * Rank of a rule for a line matched by several rules: product scope, then taxon scope (the
     * deepest taxon first), then all products; at equal scope a customer group rule wins. Ties
     * keep the oldest rule.
     */
    public function precedence(ContainerRuleInterface $rule): int
    {
        $rank = match ($rule->getScope()) {
            ContainerRuleInterface::SCOPE_PRODUCT => 1000000,
            ContainerRuleInterface::SCOPE_TAXON => 1000 + min(998, (int) $rule->getTaxon()?->getLevel()),
            default => 0,
        };

        return $rank * 2 + (null !== $rule->getCustomerGroup() ? 1 : 0);
    }

    /** Scope identity: a rule of the customer's group replaces the rules without group of the same key. */
    public function scopeKey(ContainerRuleInterface $rule): string
    {
        return match ($rule->getScope()) {
            ContainerRuleInterface::SCOPE_PRODUCT => 'product:' . EntityId::string($rule->getProduct()?->getId()),
            ContainerRuleInterface::SCOPE_TAXON => 'taxon:' . EntityId::string($rule->getTaxon()?->getId()),
            default => 'all',
        };
    }

    private function inTaxon(ProductInterface $product, TaxonInterface $taxon): bool
    {
        $taxons = $product->getTaxons()->toArray();
        if (null !== $product->getMainTaxon()) {
            $taxons[] = $product->getMainTaxon();
        }
        foreach ($taxons as $candidate) {
            if ($candidate->getCode() === $taxon->getCode()) {
                return true;
            }
            foreach ($candidate->getAncestors() as $ancestor) {
                if ($ancestor->getCode() === $taxon->getCode()) {
                    return true;
                }
            }
        }

        return false;
    }
}
