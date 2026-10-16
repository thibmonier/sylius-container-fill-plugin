<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Integration;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillChecker;
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

    public function testALineWithoutLogisticsDataIsIgnoredAndDoesNotBlockAMixedCart(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $complete = $this->catalog->variant('A', [[$pallet, 40, null]]);
        $plain = $this->catalog->plainVariant('PLAIN');

        $report = $this->checker()->check($this->catalog->cart([[$complete, 40], [$plain, 3]]));

        self::assertTrue($report->isSatisfied());
        self::assertCount(1, $report->outcomes);
        self::assertSame([(string) $complete->getCode() => 40], $report->outcomes[0]->result->containers[0]->units);
        self::assertNull($report->outcomes[0]->result->line((string) $plain->getCode()));
    }

    public function testACartWithoutAnyLogisticsDataIsAccepted(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 1);

        $report = $this->checker()->check($this->catalog->cart([[$this->catalog->plainVariant('PLAIN'), 1], [$this->catalog->plainVariant('OTHER'), 2]]));

        self::assertTrue($report->isSatisfied());
        self::assertSame([], $report->outcomes);
    }

    public function testNullOrNegativeWeightIsIgnored(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $zero = $this->catalog->variant('ZERO', [[$pallet, 40, null]], null);
        $zero->setWeight(0.0);
        $negative = $this->catalog->variant('NEG', [[$pallet, 40, null]], null);
        $negative->setWeight(-3.0);
        $this->entityManager()->flush();

        $report = $this->checker()->check($this->catalog->cart([[$zero, 5], [$negative, 7]]));

        self::assertTrue($report->isSatisfied());
        self::assertSame([], $report->outcomes);
    }

    public function testCompleteLinesAreStillPlannedAsBefore(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $variant = $this->catalog->variant('A', [[$pallet, 40, 8]]);

        $report = $this->checker()->check($this->catalog->cart([[$variant, 50]]));

        self::assertFalse($report->isSatisfied());
        self::assertSame(Violation::NOT_FULL, $report->outcomes[0]->violations[0]->code);
        self::assertSame(2, $report->outcomes[0]->result->containerCount());
    }

    public function testACorruptedValueIsDiscardedWithAWarningNamingTheVariant(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $complete = $this->catalog->variant('A', [[$pallet, 40, null]]);
        $corrupted = $this->catalog->variant('CORRUPTED', [[$pallet, 40, null]]);
        $cart = $this->catalog->cart([[$complete, 40], [$corrupted, 3]]);
        // Not flushed: a non-finite float cannot be stored, only a corrupted import could bring one.
        $corrupted->setWidth(10);
        $corrupted->setHeight(10);
        $corrupted->setDepth(\NAN);

        $logger = new class() extends AbstractLogger {
            /** @var list<array{string, string, array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        $report = $this->checkerWith($logger)->check($cart);

        self::assertTrue($report->isSatisfied());
        self::assertNull($report->outcomes[0]->result->line((string) $corrupted->getCode()));
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0][0]);
        self::assertSame((string) $corrupted->getCode(), $logger->records[0][2]['variant']);
    }

    private function checkerWith(LoggerInterface $logger): CartFillChecker
    {
        $container = self::getContainer();

        return new CartFillChecker(
            $container->get('tmonier_container_fill.repository.container_rule'),
            $container->get('tmonier_container_fill.logistics_provider'),
            $container->get('tmonier_container_fill.planner'),
            $container->get('tmonier_container_fill.rule_evaluator'),
            $container->get('tmonier_container_fill.suggestion_finder'),
            $container->get('tmonier_container_fill.scope_matcher'),
            $logger,
        );
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

    public function testEachLineIsPlannedOnceByTheMostSpecificRuleButEveryRuleIsChecked(): void
    {
        $pallet = $this->catalog->pallet();
        $parent = $this->catalog->taxon('GOODS');
        $child = $this->catalog->taxon('DRINKS', $parent);
        $general = $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 10);
        $parentRule = $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 20, $parent);
        $childRule = $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_FULL_ONLY, null, $child);
        $drink = $this->catalog->variant('DRINK', [[$pallet, 40, null]], 1000, 1, false, false, $child);
        $other = $this->catalog->variant('OTHER', [[$pallet, 40, null]], 1000);

        $report = $this->checker()->check($this->catalog->cart([[$drink, 40], [$other, 10]]));

        // Blocking: the three rules are checked (on 2, 1 and 1 lines); plan: each line once.
        self::assertCount(3, $report->outcomes);
        self::assertCount(2, $report->plan);
        self::assertSame(2, $report->containerCount());
        $byRule = [];
        foreach ($report->plan as $outcome) {
            $byRule[(int) $outcome->rule->id] = array_keys($outcome->result->containers[0]->units);
        }
        self::assertEqualsCanonicalizing([$childRule->getId(), $general->getId()], array_keys($byRule));
        self::assertSame([(string) $drink->getCode()], $byRule[(int) $childRule->getId()]);
        self::assertSame([(string) $other->getCode()], $byRule[(int) $general->getId()]);
        self::assertArrayNotHasKey((int) $parentRule->getId(), $byRule);
        // The general rule is checked on both lines: its own containers differ from the plan.
        $data = $report->toArray();
        self::assertIsArray($data['rules']);
        self::assertSame([false, false, true], array_map(static fn (mixed $rule): mixed => \is_array($rule) ? $rule['planned'] : null, $data['rules']));
        self::assertTrue($report->isSatisfied());
    }

    public function testTwoRulesOfTheSameScopeCombine(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MIN_FILL_PERCENT, 50);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MAX_CONTAINERS, 1);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], 1000);

        $report = $this->checker()->check($this->catalog->cart([[$variant, 120]]));

        self::assertFalse($report->isSatisfied());
        self::assertSame([Violation::TOO_MANY_CONTAINERS], array_map(static fn (Violation $violation): string => $violation->code, $report->violations()));
        self::assertSame(3, $report->containerCount());
        self::assertTrue($this->checker()->check($this->catalog->cart([[$variant, 40]]))->isSatisfied());
    }

    public function testAGlobalCapSeesTheLinesOfMoreSpecificRules(): void
    {
        $pallet = $this->catalog->pallet();
        $taxon = $this->catalog->taxon('PALLETS');
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_MAX_CONTAINERS, 1);
        $this->catalog->rule([$pallet], ContainerRuleInterface::MODE_FULL_ONLY, null, $taxon);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]], 1000, taxon: $taxon);

        $report = $this->checker()->check($this->catalog->cart([[$variant, 120]]));

        self::assertFalse($report->isSatisfied());
        self::assertContains(Violation::TOO_MANY_CONTAINERS, array_map(static fn (Violation $violation): string => $violation->code, $report->violations()));
        self::assertSame(3, $report->containerCount());
    }

    public function testARuleWithoutEnabledContainerTypeBlocks(): void
    {
        $pallet = $this->catalog->pallet();
        $this->catalog->rule([$pallet]);
        $variant = $this->catalog->variant('A', [[$pallet, 40, null]]);
        $pallet->setEnabled(false);
        $this->entityManager()->flush();

        $report = $this->checker()->check($this->catalog->cart([[$variant, 80]]));

        self::assertFalse($report->isSatisfied());
        self::assertSame(Violation::NO_CONTAINER_TYPE, $report->lineViolations[0]->code);
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
