<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\Rule;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\Unit\Planner\PlannerFixtures;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;
use Tmonier\SyliusContainerFillPlugin\Planner\SlotWeightPlanner;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleEvaluator;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleSpec;
use Tmonier\SyliusContainerFillPlugin\Rule\Suggestion;
use Tmonier\SyliusContainerFillPlugin\Rule\SuggestionFinder;

final class SuggestionFinderTest extends TestCase
{
    private SlotWeightPlanner $planner;

    private SuggestionFinder $finder;

    protected function setUp(): void
    {
        $this->planner = new SlotWeightPlanner();
        $this->finder = new SuggestionFinder($this->planner, new RuleEvaluator());
    }

    public function testCompletesTheLastPalletBySlots(): void
    {
        $suggestion = $this->find(new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY), [PlannerFixtures::light(90)]);

        self::assertNotNull($suggestion);
        self::assertSame(Suggestion::COMPLETE, $suggestion->action);
        self::assertSame('LIGHT', $suggestion->lineKey);
        self::assertSame(30, $suggestion->addUnits);
        self::assertSame(3, $suggestion->containerIndex);
        self::assertSame(BindingConstraint::Slots, $suggestion->binding);
    }

    public function testSuggestsWholeCases(): void
    {
        $water = new PlanLine('WATER', 'Water', 300, 480, 1550.0, null, 6);
        $suggestion = $this->find(new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY), [$water]);

        self::assertSame(180, $suggestion?->addUnits);
        self::assertSame(30, $suggestion->addCases());
    }

    public function testWeightBoundPalletIsCompletedByWeight(): void
    {
        $suggestion = $this->find(new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY), [PlannerFixtures::heavy(30)]);

        // 45 boxes fill the pallet by weight (990 kg): 15 more, not 18 (places).
        self::assertSame(15, $suggestion?->addUnits);
    }

    public function testMinFillSuggestsTheSmallestAddition(): void
    {
        $suggestion = $this->find(new RuleSpec(ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 95), [PlannerFixtures::light(30)]);

        self::assertSame(8, $suggestion?->addUnits);
    }

    public function testSuggestionIsVerified(): void
    {
        $rule = new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY);
        $lines = [PlannerFixtures::light(25), PlannerFixtures::heavy(10)];
        $suggestion = $this->find($rule, $lines);
        self::assertNotNull($suggestion);

        $completed = array_map(static fn (PlanLine $line): PlanLine => $line->key === $suggestion->lineKey ? $line->withQuantity($line->quantity + $suggestion->addUnits) : $line, $lines);
        self::assertSame([], (new RuleEvaluator())->evaluate($rule, $this->planner->plan(PlannerFixtures::pallet(), $completed)));
    }

    public function testMultipleOfSuggestsRounding(): void
    {
        $water = new PlanLine('WATER', 'Water', 14, 480, 1550.0, null, 6);
        $suggestion = $this->find(new RuleSpec(ContainerRuleInterface::MODE_MULTIPLE_OF), [$water]);

        self::assertSame(Suggestion::ROUND_TO_MULTIPLE, $suggestion?->action);
        self::assertSame(4, $suggestion->addUnits);
    }

    public function testNoSuggestionWhenTheRuleIsMetOrForMaxContainers(): void
    {
        self::assertNull($this->find(new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY), [PlannerFixtures::light(40)]));
        self::assertNull($this->find(new RuleSpec(ContainerRuleInterface::MODE_MAX_CONTAINERS, 1), [PlannerFixtures::light(90)]));
        self::assertNull($this->find(new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY), [new PlanLine('X', 'X', 1, null, 1.0)]));
    }

    /** @param list<PlanLine> $lines */
    private function find(RuleSpec $rule, array $lines): ?Suggestion
    {
        return $this->finder->find($rule, $this->planner->plan(PlannerFixtures::pallet(), $lines));
    }
}
