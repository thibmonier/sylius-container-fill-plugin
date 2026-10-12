<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\FillResult;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;

/** Checks a plan against a rule (pure). */
final class RuleEvaluator
{
    private const EPSILON = 1e-6;

    /** @return list<Violation> */
    public function evaluate(RuleSpec $rule, FillResult $result): array
    {
        if (!$result->isComplete()) {
            $labels = [];
            foreach ($result->lines as $line) {
                if (isset($result->incomplete[$line->key])) {
                    $labels[] = $line->label;
                }
            }

            return [new Violation(Violation::INCOMPLETE_DATA, ['products' => implode(', ', $labels)])];
        }

        $violations = [];
        foreach ($result->containers as $container) {
            if ($container->isOverloaded()) {
                $violations[] = new Violation(Violation::OVERLOADED, ['index' => $container->index, 'percent' => $container->percent()]);
            }
        }

        switch ($rule->mode) {
            case ContainerRuleInterface::MODE_FULL_ONLY:
            case ContainerRuleInterface::MODE_MIN_FILL_PERCENT:
                $required = (float) $rule->requiredFill();
                foreach ($result->containers as $container) {
                    if ($container->full || $container->ratio() >= $required - self::EPSILON) {
                        continue;
                    }
                    $violations[] = ContainerRuleInterface::MODE_FULL_ONLY === $rule->mode
                        ? new Violation(Violation::NOT_FULL, ['index' => $container->index, 'percent' => $container->percent()])
                        : new Violation(Violation::UNDER_FILLED, ['index' => $container->index, 'percent' => $container->percent(), 'required' => (int) round($required * 100)]);
                }

                break;
            case ContainerRuleInterface::MODE_MULTIPLE_OF:
                foreach ($result->lines as $line) {
                    $multiple = $this->multipleOf($rule, $line);
                    if (0 !== $line->quantity % $multiple) {
                        $violations[] = new Violation(Violation::NOT_MULTIPLE, ['product' => $line->label, 'quantity' => $line->quantity, 'multiple' => $multiple]);
                    }
                }

                break;
            case ContainerRuleInterface::MODE_MAX_CONTAINERS:
                $maximum = max(1, $rule->threshold ?? 1);
                if ($result->containerCount() > $maximum) {
                    $violations[] = new Violation(Violation::TOO_MANY_CONTAINERS, ['count' => $result->containerCount(), 'maximum' => $maximum]);
                }

                break;
        }

        return $violations;
    }

    /** Multiple required by a "multiple_of" rule: its threshold, or the case of the line. */
    public function multipleOf(RuleSpec $rule, PlanLine $line): int
    {
        return null !== $rule->threshold && $rule->threshold > 0 ? $rule->threshold : $line->unitsPerCase;
    }
}
