<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Contracts\Service\ResetInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Logistics\EntityId;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogistics;
use Tmonier\SyliusContainerFillPlugin\Logistics\VariantLogisticsProviderInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\ContainerSpec;
use Tmonier\SyliusContainerFillPlugin\Planner\LoadPlannerInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerRuleRepositoryInterface;

/**
 * Rules of the cart channel, for everybody or for the customer's group, whose scope contains
 * at least one line. Each rule plans its lines for each allowed container type (one type per
 * rule): the first type meeting the rule is retained, else the first one with complete data.
 *
 * Results are kept for the request, keyed by the lines and a data version bumped whenever
 * plugin data or variants are flushed (CacheInvalidator).
 */
final class CartFillChecker implements CartFillCheckerInterface, ResetInterface
{
    /** @var array<string, CartFillReport> */
    private array $reports = [];

    /** @var array<int, list<ContainerRuleInterface>> */
    private array $rules = [];

    private int $version = 0;

    public function __construct(
        private readonly ContainerRuleRepositoryInterface $ruleRepository,
        private readonly VariantLogisticsProviderInterface $logisticsProvider,
        private readonly LoadPlannerInterface $planner,
        private readonly RuleEvaluator $evaluator,
        private readonly SuggestionFinder $suggestionFinder,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public function check(OrderInterface $order): CartFillReport
    {
        $channel = $order->getChannel();
        if (!$channel instanceof \Sylius\Component\Channel\Model\ChannelInterface) {
            return new CartFillReport();
        }

        /** @var array<string, array{variant: ProductVariantInterface, product: ProductInterface, quantity: int}> $lines */
        $lines = [];
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            $product = $variant?->getProduct();
            if (!$variant instanceof ProductVariantInterface || !$product instanceof ProductInterface || $item->getQuantity() < 1) {
                continue;
            }
            $key = $variant->getCode() ?? ('#' . EntityId::string($variant->getId()));
            $lines[$key] ??= ['variant' => $variant, 'product' => $product, 'quantity' => 0];
            $lines[$key]['quantity'] += $item->getQuantity();
        }
        ksort($lines);

        $group = $order->getCustomer()?->getGroup();
        $cacheKey = implode('|', [
            $this->version,
            (string) $channel->getCode(),
            (string) $group?->getCode(),
            implode(',', array_map(static fn (string $key, array $line): string => $key . ':' . $line['quantity'], array_keys($lines), $lines)),
        ]);
        if (isset($this->reports[$cacheKey])) {
            return $this->reports[$cacheKey];
        }

        if ([] === $lines) {
            return $this->reports[$cacheKey] = new CartFillReport();
        }

        $logistics = $this->logisticsProvider->getForVariants(array_values(array_map(static fn (array $line): ProductVariantInterface => $line['variant'], $lines)));

        $lineViolations = [];
        foreach ($lines as $key => $line) {
            $data = $logistics[$key] ?? null;
            if (null !== $data && $data->isCaseMultipleRequired() && 0 !== $line['quantity'] % $data->unitsPerCase()) {
                $lineViolations[] = new Violation(Violation::NOT_MULTIPLE, ['product' => $data->label(), 'quantity' => $line['quantity'], 'multiple' => $data->unitsPerCase()]);
            }
        }

        // Blocking: every applicable rule is checked on all the lines of its scope (rules combine).
        // Load plan: each line is planned once, by the most specific rule matching it (see
        // ScopeMatcher::precedence()); used to count containers (shipping) and for display.
        $rules = $this->applicableRules(EntityId::int($channel->getId()), $channel, $group?->getCode());
        $inScope = [];
        $planned = [];
        foreach ($lines as $key => $line) {
            if (!isset($logistics[$key])) {
                continue;
            }
            $winner = null;
            foreach ($rules as $index => $rule) {
                if (!$this->scopeMatcher->matches($rule, $line['product'])) {
                    continue;
                }
                $inScope[$index][$key] = $line['quantity'];
                if (null === $winner || $this->scopeMatcher->precedence($rule) > $this->scopeMatcher->precedence($rules[$winner])) {
                    $winner = $index;
                }
            }
            if (null !== $winner) {
                $planned[$winner][$key] = $line['quantity'];
            }
        }

        $outcomes = [];
        $plan = [];
        foreach ($rules as $index => $rule) {
            if (!isset($inScope[$index])) {
                continue;
            }
            if (!$this->hasEnabledContainerType($rule)) {
                $lineViolations[] = new Violation(Violation::NO_CONTAINER_TYPE, ['rule' => (string) $rule->getName()]);

                continue;
            }
            $outcome = $this->evaluateRule($rule, $inScope[$index], $logistics);
            if (!$outcome instanceof \Tmonier\SyliusContainerFillPlugin\Rule\RuleOutcome) {
                continue;
            }
            $outcomes[] = $outcome;
            if (isset($planned[$index])) {
                $plan[] = $planned[$index] === $inScope[$index] ? $outcome : ($this->evaluateRule($rule, $planned[$index], $logistics) ?? $outcome);
            }
        }

        return $this->reports[$cacheKey] = new CartFillReport($outcomes, $lineViolations, $plan);
    }

    public function invalidate(): void
    {
        ++$this->version;
        $this->reports = [];
        $this->rules = [];
    }

    public function reset(): void
    {
        $this->reports = [];
        $this->rules = [];
    }

    /**
     * @param array<string, int> $quantities
     * @param array<string, VariantLogistics> $logistics
     */
    private function evaluateRule(ContainerRuleInterface $rule, array $quantities, array $logistics): ?RuleOutcome
    {
        $spec = RuleSpec::fromRule($rule);
        $types = array_values(array_filter($rule->getContainerTypes()->toArray(), static fn (ContainerTypeInterface $type): bool => $type->isEnabled()));

        $first = null;
        $firstComplete = null;
        foreach ($types as $type) {
            $planLines = [];
            foreach ($quantities as $key => $quantity) {
                $planLines[] = $logistics[$key]->planLine($type, $quantity);
            }
            $result = $this->planner->plan(ContainerSpec::fromContainerType($type), $planLines);
            $violations = $this->evaluator->evaluate($spec, $result);
            if ([] === $violations) {
                return new RuleOutcome($spec, $result, []);
            }
            $first ??= [$result, $violations];
            if (null === $firstComplete && $result->isComplete()) {
                $firstComplete = [$result, $violations];
            }
        }

        $retained = $firstComplete ?? $first;
        if (null === $retained) {
            return null;
        }
        [$result, $violations] = $retained;

        return new RuleOutcome($spec, $result, $violations, $this->suggestionFinder->find($spec, $result));
    }

    private function hasEnabledContainerType(ContainerRuleInterface $rule): bool
    {
        foreach ($rule->getContainerTypes() as $type) {
            if ($type->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<ContainerRuleInterface> */
    private function applicableRules(int $channelId, \Sylius\Component\Channel\Model\ChannelInterface $channel, ?string $groupCode): array
    {
        $this->rules[$channelId] ??= $this->ruleRepository->findEnabledByChannel($channel);

        $candidates = array_values(array_filter(
            $this->rules[$channelId],
            static fn (ContainerRuleInterface $rule): bool => !$rule->getCustomerGroup() instanceof \Sylius\Component\Customer\Model\CustomerGroupInterface || (null !== $groupCode && $rule->getCustomerGroup()->getCode() === $groupCode),
        ));

        $groupScopes = [];
        foreach ($candidates as $rule) {
            if (null !== $rule->getCustomerGroup()) {
                $groupScopes[$this->scopeMatcher->scopeKey($rule)] = true;
            }
        }

        return array_values(array_filter(
            $candidates,
            fn (ContainerRuleInterface $rule): bool => $rule->getCustomerGroup() instanceof \Sylius\Component\Customer\Model\CustomerGroupInterface || !isset($groupScopes[$this->scopeMatcher->scopeKey($rule)]),
        ));
    }
}
