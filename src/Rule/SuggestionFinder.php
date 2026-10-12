<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;
use Tmonier\SyliusContainerFillPlugin\Planner\ContainerFill;
use Tmonier\SyliusContainerFillPlugin\Planner\FillResult;
use Tmonier\SyliusContainerFillPlugin\Planner\LoadPlannerInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;

/**
 * Finds the smallest quantity to add to one line so that the rule is met, checked by planning
 * again (the split may move units between containers). Only additions are suggested.
 */
final class SuggestionFinder
{
    /** Cases tried after the estimate before giving up. */
    private const EXTRA_ATTEMPTS = 12;

    /** Lines of the least filled container tried, by units in it. */
    private const CANDIDATES = 3;

    public function __construct(
        private readonly LoadPlannerInterface $planner,
        private readonly RuleEvaluator $evaluator,
    ) {
    }

    public function find(RuleSpec $rule, FillResult $result): ?Suggestion
    {
        if (!$result->isComplete() || [] === $result->containers) {
            return null;
        }

        if (ContainerRuleInterface::MODE_MULTIPLE_OF === $rule->mode) {
            foreach ($result->lines as $line) {
                $multiple = $this->evaluator->multipleOf($rule, $line);
                $remainder = $line->quantity % $multiple;
                if (0 !== $remainder) {
                    $least = $result->leastFilled();

                    return new Suggestion(Suggestion::ROUND_TO_MULTIPLE, $line->key, $line->label, $multiple - $remainder, null === $least ? 1 : $least->index, null === $least ? BindingConstraint::Slots : $least->binding(), $line->unitsPerCase);
                }
            }

            return null;
        }

        $required = $rule->requiredFill();
        if (null === $required) {
            return null;
        }

        $target = $this->target($result, $required);
        if (null === $target) {
            return null;
        }

        $candidates = $target->units;
        arsort($candidates);
        foreach (\array_slice(array_keys($candidates), 0, self::CANDIDATES) as $key) {
            $line = $result->line((string) $key);
            if (null === $line) {
                continue;
            }
            $suggestion = $this->tryLine($rule, $result, $target, $line, $required);
            if (null !== $suggestion) {
                return $suggestion;
            }
        }

        return null;
    }

    private function target(FillResult $result, float $required): ?ContainerFill
    {
        $target = null;
        foreach ($result->containers as $container) {
            if ($container->full || $container->ratio() >= $required - 1e-6) {
                continue;
            }
            if (null === $target || $container->ratio() <= $target->ratio()) {
                $target = $container;
            }
        }

        return $target;
    }

    private function tryLine(RuleSpec $rule, FillResult $result, ContainerFill $target, PlanLine $line, float $required): ?Suggestion
    {
        $case = $line->unitsPerCase;
        $estimate = $this->estimate($result, $target, $line, $required);
        // Start one case below the estimate (rounding), aligned on whole cases of the line.
        $start = max($case, (int) (ceil(($line->quantity + $estimate) / $case) * $case) - $case);
        for ($attempt = 0; $attempt <= self::EXTRA_ATTEMPTS; ++$attempt) {
            $quantity = $start + $attempt * $case;
            if ($quantity <= $line->quantity) {
                continue;
            }
            $lines = array_map(static fn (PlanLine $other): PlanLine => $other->key === $line->key ? $other->withQuantity($quantity) : $other, $result->lines);
            $plan = $this->planner->plan($result->container, $lines);
            if ([] === $this->evaluator->evaluate($rule, $plan)) {
                return new Suggestion(Suggestion::COMPLETE, $line->key, $line->label, $quantity - $line->quantity, $target->index, $target->binding(), $case);
            }
        }

        return null;
    }

    /** Units of the line that bring the target container to the required fill in its first saturated dimension. */
    private function estimate(FillResult $result, ContainerFill $target, PlanLine $line, float $required): int
    {
        $perUnit = [[$target->weightRatio, (float) $line->unitWeight / $result->container->maxPayload]];
        if (!$result->layerMode) {
            $perUnit[] = [$target->slotRatio, $line->slotShare()];
        }
        // Layer mode: share of the stack taken by one unit (a layer of upl units).
        $spec = $result->container;
        if ($result->layerMode && null !== $target->layerRatio && $line->hasLayerData()) {
            $perLayer = (float) $line->unitsPerLayer;
            $share = 0.0;
            if (null !== $spec->maxStackHeight && $spec->maxStackHeight > 0) {
                $share = (float) $line->layerHeight / $spec->maxStackHeight / $perLayer;
            }
            if (null !== $spec->maxLayers && $spec->maxLayers > 0) {
                $share = max($share, 1 / $spec->maxLayers / $perLayer);
            }
            $perUnit[] = [$target->layerRatio, $share];
        }

        $estimate = \PHP_INT_MAX;
        foreach ($perUnit as [$used, $share]) {
            if ($share > 0.0) {
                $estimate = min($estimate, (int) ceil(max(0.0, $required - $used) / $share - 1e-9));
            }
        }

        return \PHP_INT_MAX === $estimate ? $line->unitsPerCase : max(1, $estimate);
    }
}
