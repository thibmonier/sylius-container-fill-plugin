<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

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

    /** Scope identity: a rule of the customer's group replaces the rules without group of the same key. */
    public function scopeKey(ContainerRuleInterface $rule): string
    {
        return match ($rule->getScope()) {
            ContainerRuleInterface::SCOPE_PRODUCT => 'product:' . (string) $rule->getProduct()?->getId(),
            ContainerRuleInterface::SCOPE_TAXON => 'taxon:' . (string) $rule->getTaxon()?->getId(),
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
            if (!$candidate instanceof TaxonInterface) {
                continue;
            }
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
