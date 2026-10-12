<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\Planner;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;
use Tmonier\SyliusContainerFillPlugin\Planner\ContainerSpec;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;
use Tmonier\SyliusContainerFillPlugin\Planner\SlotWeightPlanner;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleEvaluator;
use Tmonier\SyliusContainerFillPlugin\Rule\RuleSpec;
use Tmonier\SyliusContainerFillPlugin\Rule\SuggestionFinder;

/** Layer mode (1.1): a pallet is a stack of layers limited by its height, layer count and payload. */
final class LayerPlannerTest extends TestCase
{
    private SlotWeightPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new SlotWeightPlanner();
    }

    public function testAStackOfLayers(): void
    {
        // 60 units per layer, 200 mm per layer, 1600 mm: 8 layers.
        $result = $this->planner->plan(self::pallet(), [self::water(300)]);

        self::assertTrue($result->layerMode);
        self::assertSame(1, $result->containerCount());
        $pallet = $result->containers[0];
        self::assertCount(5, $pallet->layers);
        self::assertEqualsWithDelta(0.625, $pallet->layerRatio, 1e-9);
        self::assertSame(62, $pallet->percent());
        self::assertSame(BindingConstraint::Layers, $pallet->binding());
        self::assertSame(600, $pallet->remainingHeight);
        self::assertFalse($pallet->full);
        self::assertSame([['key' => 'WATER', 'label' => 'Water', 'count' => 5, 'units' => 60, 'unitsPerLayer' => 60]], $pallet->layerGroups());
    }

    public function testAPartialTopLayerTakesItsHeightButNotTheFill(): void
    {
        $result = $this->planner->plan(self::pallet(), [self::water(421)]);

        $pallet = $result->containers[0];
        self::assertCount(8, $pallet->layers);
        self::assertSame(0, $pallet->remainingHeight);
        self::assertSame(87, $pallet->percent());
        self::assertFalse($pallet->full, 'The partial layer can be completed.');
        self::assertTrue($pallet->hasPartialLayer());

        $full = $this->planner->plan(self::pallet(), [self::water(480)])->containers[0];
        self::assertTrue($full->full);
        self::assertSame(100, $full->percent());
    }

    public function testLayersAreSplitIntoContainers(): void
    {
        $result = $this->planner->plan(self::pallet(), [self::water(600)]);

        self::assertSame(2, $result->containerCount());
        self::assertCount(8, $result->containers[0]->layers);
        self::assertCount(2, $result->containers[1]->layers);
        self::assertSame(480, $result->containers[0]->units['WATER']);
    }

    public function testThePayloadStillBinds(): void
    {
        // 5 bags of 25 kg per layer, 120 mm: 8 layers = 1000 kg on a 1000 kg pallet before the height.
        $result = $this->planner->plan(self::pallet(), [new PlanLine('CEMENT', 'Cement', 45, 40, 25000.0, null, 1, false, 5, 120)]);

        self::assertSame(2, $result->containerCount());
        self::assertSame(40, $result->containers[0]->units['CEMENT']);
        self::assertSame(BindingConstraint::Weight, $result->containers[0]->binding());
        self::assertTrue($result->containers[0]->full);
    }

    public function testNonStackableProductsEndAStack(): void
    {
        $tiles = new PlanLine('TILES', 'Tiles', 16, 48, 1000.0, null, 1, false, 8, 250, false);

        $result = $this->planner->plan(self::pallet(), [self::water(120), $tiles]);

        self::assertSame(2, $result->containerCount());
        self::assertSame(['WATER', 'WATER', 'TILES'], array_column($result->containers[0]->layers, 'key'));
        self::assertTrue($result->containers[0]->full, 'Nothing goes on a non-stackable layer.');
        self::assertSame(['TILES'], array_column($result->containers[1]->layers, 'key'));
    }

    public function testAMaximumNumberOfLayersWithoutHeight(): void
    {
        $pallet = new ContainerSpec('EUR', 'EUR pallet', 'pallet', 1, 1000000, null, false, true, null, 4);

        $result = $this->planner->plan($pallet, [self::water(300)]);

        self::assertSame(2, $result->containerCount());
        self::assertCount(4, $result->containers[0]->layers);
        self::assertNull($result->containers[0]->remainingHeight);
    }

    public function testCapacitiesWhenALineHasNoLayerData(): void
    {
        $result = $this->planner->plan(self::pallet(), [self::water(300), new PlanLine('LOOSE', 'Loose', 10, 40, 1000.0)]);

        self::assertFalse($result->layerMode);
        self::assertSame([], $result->containers[0]->layers);
    }

    public function testLayerModeIsOptInAndForSinglePlaces(): void
    {
        $capacityMode = new ContainerSpec('EUR', 'EUR pallet', 'pallet', 1, 1000000, null, false, false, 1600);
        self::assertFalse($this->planner->plan($capacityMode, [self::water(300)])->layerMode);

        $truck = new ContainerSpec('SEMI', 'Semi', 'truck', 33, 24000000, null, false, true, 2700);
        self::assertFalse($truck->supportsLayers());
        self::assertFalse($this->planner->plan($truck, [self::water(300)])->layerMode);
    }

    public function testOwnPalletLayersStayAlone(): void
    {
        $own = new PlanLine('OWN', 'Own', 60, 480, 100.0, null, 1, true, 60, 200);

        $result = $this->planner->plan(self::pallet(), [self::water(60), $own]);

        self::assertSame(2, $result->containerCount());
    }

    public function testFullOnlyRuleAndSuggestionCountLayers(): void
    {
        $evaluator = new RuleEvaluator();
        $rule = new RuleSpec(ContainerRuleInterface::MODE_FULL_ONLY);
        $result = $this->planner->plan(self::pallet(), [self::water(421)]);

        self::assertNotSame([], $evaluator->evaluate($rule, $result));
        $suggestion = (new SuggestionFinder($this->planner, $evaluator))->find($rule, $result);
        self::assertNotNull($suggestion);
        self::assertSame(59, $suggestion->addUnits);
        self::assertSame([], $evaluator->evaluate($rule, $this->planner->plan(self::pallet(), [self::water(480)])));
    }

    private static function pallet(): ContainerSpec
    {
        return new ContainerSpec('EUR', 'EUR pallet', 'pallet', 1, 1000000, null, false, true, 1600);
    }

    /** 1.55 kg units, 60 per layer, 200 mm layers, 480 per pallet by capacity. */
    private static function water(int $quantity): PlanLine
    {
        return new PlanLine('WATER', 'Water', $quantity, 480, 1550.0, null, 1, false, 60, 200);
    }
}
