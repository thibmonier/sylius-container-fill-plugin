<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;
use Tmonier\SyliusContainerFillPlugin\Rule\Violation;

final class CartFillCheckerTest extends KernelTestCase
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

    public function testNoRuleNoOutcome(): void
    {
        $pallet = $this->catalog->pallet();
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 7]]);

        $report = $this->checker()->check($cart);

        self::assertFalse($report->hasRules());
        self::assertTrue($report->isSatisfied());
    }

    public function testFullPalletsOnlyWithSuggestion(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $variant = $this->catalog->variant('A', [[$pallet, 40, 8]]);

        $report = $this->checker()->check($this->catalog->cart([[$variant, 50]]));

        self::assertFalse($report->isSatisfied());
        $outcome = $report->outcomes[0];
        self::assertSame(2, $outcome->result->containerCount());
        self::assertSame(Violation::NOT_FULL, $outcome->violations[0]->code);
        self::assertSame(30, $outcome->suggestion?->addUnits);
        self::assertSame((string) $variant->getCode(), $outcome->suggestion->lineKey);

        self::assertTrue($this->checker()->check($this->catalog->cart([[$variant, 80]]))->isSatisfied());
    }

    public function testFirstAllowedTypeMeetingTheRuleIsRetained(): void
    {
        $pallet = $this->catalog->pallet();
        $truck = $this->catalog->truck($pallet, 'TRUCK', 2);
        $this->catalog->rule([$pallet, $truck], ContainerRuleInterface::MODE_MAX_CONTAINERS, 1);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], 1000);

        // 70 units: 2 pallets (refused) but one truck of 2 places (80 units).
        $report = $this->checker()->check($this->catalog->cart([[$variant, 70]]));

        self::assertTrue($report->isSatisfied());
        self::assertSame($truck->getCode(), $report->outcomes[0]->result->container->code);
    }

    public function testVariantsWithoutLogisticsDataInScopeBlock(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 1);
        $plain = $this->catalog->plainVariant('PLAIN');

        $report = $this->checker()->check($this->catalog->cart([[$plain, 1]]));

        self::assertFalse($report->isSatisfied());
        self::assertSame(Violation::INCOMPLETE_DATA, $report->outcomes[0]->violations[0]->code);
    }

    public function testSyliusWeightIsTheFallback(): void
    {
        $pallet = $this->catalog->pallet('PAL', 100);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 1);
        $variant = $this->catalog->variant('A', [[$pallet, 1000, null]], null);
        $variant->setWeight(2.5); // kg (test configuration)
        $this->entityManager()->flush();

        $result = $this->checker()->check($this->catalog->cart([[$variant, 20]]))->outcomes[0]->result;

        self::assertSame(BindingConstraint::Weight, $result->containers[0]->binding());
        self::assertSame(50, $result->containers[0]->percent());
    }

    public function testTaxonScopeIncludesDescendants(): void
    {
        $pallet = $this->catalog->pallet();
        $root = $this->catalog->taxon('ROOT');
        $child = $this->catalog->taxon('CHILD', $root);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_FULL_ONLY, null, $root);
        $inScope = $this->catalog->variant('IN', [[$pallet, 40, null]], 10000, 1, false, false, $child);
        $outOfScope = $this->catalog->variant('OUT', [[$pallet, 40, null]]);

        $report = $this->checker()->check($this->catalog->cart([[$inScope, 40], [$outOfScope, 3]]));

        self::assertTrue($report->isSatisfied());
        self::assertSame([(string) $inScope->getCode() => 40], $report->outcomes[0]->result->containers[0]->units);
    }

    public function testAGroupRuleReplacesTheGeneralRuleOfTheSameScope(): void
    {
        $pallet = $this->catalog->pallet();
        $group = $this->catalog->group('WHOLESALE');
        $this->catalog->rule([$pallet]);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 50, null, null, $group);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]]);

        self::assertFalse($this->checker()->check($this->catalog->cart([[$variant, 30]], $this->catalog->customer('retail')))->isSatisfied());
        $report = $this->checker()->check($this->catalog->cart([[$variant, 30]], $this->catalog->customer('pro', $group)));
        self::assertTrue($report->isSatisfied());
        self::assertCount(1, $report->outcomes);
    }

    public function testCaseMultipleLines(): void
    {
        $pallet = $this->catalog->pallet();
        $variant = $this->catalog->variant('WATER', [[$pallet, 480, null]], 9300, 6, false, true);

        $report = $this->checker()->check($this->catalog->cart([[$variant, 14]]));

        self::assertFalse($report->isSatisfied());
        self::assertSame(Violation::NOT_MULTIPLE, $report->lineViolations[0]->code);
        self::assertTrue($this->checker()->check($this->catalog->cart([[$variant, 18]]))->isSatisfied());
    }

    public function testResultsFollowDataChangesWithinTheRequest(): void
    {
        $pallet = $this->catalog->pallet();
        $rule = $this->catalog->rule([$pallet]);
        $cart = $this->catalog->cart([[$this->catalog->variant('A', [[$pallet, 40, null]]), 30]]);
        self::assertFalse($this->checker()->check($cart)->isSatisfied());

        $rule->setMode(ContainerRuleInterface::MODE_MIN_FILL_PERCENT);
        $rule->setThreshold(50);
        $this->entityManager()->flush();

        self::assertTrue($this->checker()->check($cart)->isSatisfied());
    }
}
