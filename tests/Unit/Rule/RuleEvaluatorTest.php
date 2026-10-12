<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\Rule;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\Unit\Planner\PlannerFixtures;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;
use Tmonier\SyliusContainerFillPlugin\Planner\SlotWeightPlanner;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleEvaluator;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleSpec;
use Tmonier\SyliusContainerFillPlugin\Rule\Violation;

final class RuleEvaluatorTest extends TestCase
{
    private SlotWeightPlanner $planner;

    private RuleEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->planner = new SlotWeightPlanner();
        $this->evaluator = new RuleEvaluator();
    }

    public function testFullOnly(): void
    {
        $rule = new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY);

        self::assertSame([], $this->evaluate($rule, [PlannerFixtures::light(80)]));

        $violations = $this->evaluate($rule, [PlannerFixtures::light(90)]);
        self::assertCount(1, $violations);
        self::assertSame(Violation::NOT_FULL, $violations[0]->code);
        self::assertSame(['index' => 3, 'percent' => 25], $violations[0]->parameters);
    }

    public function testFullByWeightCountsAsFull(): void
    {
        // 45 heavy boxes = 990 kg: no further box fits, the pallet is full.
        self::assertSame([], $this->evaluate(new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY), [PlannerFixtures::heavy(45)]));
    }

    public function testMinFillPercent(): void
    {
        $rule = new RuleSpec(ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 95);

        self::assertSame([], $this->evaluate($rule, [PlannerFixtures::light(38)]));
        $violations = $this->evaluate($rule, [PlannerFixtures::light(37)]);
        self::assertSame(Violation::UNDER_FILLED, $violations[0]->code);
        self::assertSame(['index' => 1, 'percent' => 92, 'required' => 95], $violations[0]->parameters);
        self::assertSame(['%index%' => 1, '%percent%' => 92, '%required%' => 95], $violations[0]->translationParameters());
    }

    public function testMultipleOfUsesTheThresholdOrTheCase(): void
    {
        $case = new PlanLine('WATER', 'Water', 14, 480, 1550.0, null, 6);
        $violations = $this->evaluate(new RuleSpec(ContainerRuleInterface::MODE_MULTIPLE_OF), [$case]);
        self::assertSame(Violation::NOT_MULTIPLE, $violations[0]->code);
        self::assertSame(['product' => 'Water', 'quantity' => 14, 'multiple' => 6], $violations[0]->parameters);

        self::assertSame([], $this->evaluate(new RuleSpec(ContainerRuleInterface::MODE_MULTIPLE_OF, 7), [$case]));
    }

    public function testMaxContainers(): void
    {
        $rule = new RuleSpec(ContainerRuleInterface::MODE_MAX_CONTAINERS, 2);

        self::assertSame([], $this->evaluate($rule, [PlannerFixtures::light(80)]));
        $violations = $this->evaluate($rule, [PlannerFixtures::light(81)]);
        self::assertSame(Violation::TOO_MANY_CONTAINERS, $violations[0]->code);
        self::assertSame(['count' => 3, 'maximum' => 2], $violations[0]->parameters);
    }

    public function testIncompleteDataAlwaysViolates(): void
    {
        $violations = $this->evaluate(new RuleSpec(ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 1), [new PlanLine('X', 'Mystery', 1, null, null)]);

        self::assertSame(Violation::INCOMPLETE_DATA, $violations[0]->code);
        self::assertSame(['products' => 'Mystery'], $violations[0]->parameters);
    }

    public function testOverloadedContainersViolate(): void
    {
        $violations = $this->evaluate(new RuleSpec(ContainerRuleInterface::MODE_MAX_CONTAINERS, 5), [new PlanLine('ANVIL', 'Anvil', 1, 1, 2000000.0)]);

        self::assertSame(Violation::OVERLOADED, $violations[0]->code);
    }

    public function testUnknownModeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RuleSpec('whatever');
    }

    /**
     * @param list<PlanLine> $lines
     *
     * @return list<Violation>
     */
    private function evaluate(RuleSpec $rule, array $lines): array
    {
        return $this->evaluator->evaluate($rule, $this->planner->plan(PlannerFixtures::pallet(), $lines));
    }
}
