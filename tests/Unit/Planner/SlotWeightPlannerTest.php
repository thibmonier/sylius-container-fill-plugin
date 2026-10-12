<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\Planner;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusContainerFillPlugin\Planner\BindingConstraint;
use Tmonier\SyliusContainerFillPlugin\Planner\ContainerSpec;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;
use Tmonier\SyliusContainerFillPlugin\Planner\SlotWeightPlanner;

final class SlotWeightPlannerTest extends TestCase
{
    private SlotWeightPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new SlotWeightPlanner();
    }

    public function testEmptyLinesGiveAnEmptyPlan(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [PlannerFixtures::light(0)]);

        self::assertSame(0, $result->containerCount());
        self::assertTrue($result->isComplete());
        self::assertNull($result->leastFilled());
    }

    public function testOneFullPalletBySlots(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [PlannerFixtures::light(40)]);

        self::assertSame(1, $result->containerCount());
        $pallet = $result->containers[0];
        self::assertEqualsWithDelta(1.0, $pallet->slotRatio, 1e-9);
        self::assertEqualsWithDelta(0.4, $pallet->weightRatio, 1e-9);
        self::assertSame(100, $pallet->percent());
        self::assertSame(BindingConstraint::Slots, $pallet->binding());
        self::assertTrue($pallet->full);
        self::assertSame(['LIGHT' => 40], $pallet->units);
    }

    public function testSplitIntoSeveralContainers(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [PlannerFixtures::light(90)]);

        self::assertSame(3, $result->containerCount());
        self::assertSame([40, 40, 10], array_map(static fn ($container): int => $container->unitCount(), $result->containers));
        self::assertSame(25, $result->leastFilled()?->percent());
        self::assertSame(3, $result->leastFilled()->index);
        self::assertFalse($result->containers[2]->full);
    }

    public function testWeightBindsBeforeSlots(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [PlannerFixtures::heavy(48)]);

        // 45 boxes = 990 kg: the 46th does not fit, a second pallet takes 3.
        self::assertSame(2, $result->containerCount());
        self::assertSame(45, $result->containers[0]->unitCount());
        self::assertSame(BindingConstraint::Weight, $result->containers[0]->binding());
        self::assertSame(99, $result->containers[0]->percent());
        self::assertTrue($result->containers[0]->full, 'No further box fits: full by weight.');
        self::assertSame(3, $result->containers[1]->unitCount());
    }

    public function testATruckFullOfPlacesCanBeOverweight(): void
    {
        // 33 places x 40 cases of 25 kg = 33 t > 24 t: the payload limits the truck.
        $line = new PlanLine('BEER', 'Beer', 1320, 1320, 25000.0);
        $result = $this->planner->plan(PlannerFixtures::truck(), [$line]);

        self::assertSame(2, $result->containerCount());
        self::assertSame(BindingConstraint::Weight, $result->containers[0]->binding());
        self::assertSame(960, $result->containers[0]->unitCount());
    }

    public function testMixedLinesShareContainersLargestFirst(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [PlannerFixtures::light(20), PlannerFixtures::heavy(24)]);

        // 24 heavy boxes = 0.5 places + 528 kg; 20 light cases = 0.5 places + 200 kg.
        self::assertSame(1, $result->containerCount());
        self::assertEqualsWithDelta(1.0, $result->containers[0]->slotRatio, 1e-9);
        self::assertEqualsWithDelta(0.728, $result->containers[0]->weightRatio, 1e-9);
        // Largest case first: a light case takes 2.5 % of the places, a heavy box 2.2 % of the payload.
        self::assertSame(['LIGHT' => 20, 'HEAVY' => 24], $result->containers[0]->units);
    }

    public function testMissingCapacityOrWeightMakesThePlanIncomplete(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [
            PlannerFixtures::light(10),
            new PlanLine('NO_CAPACITY', 'No capacity', 5, null, 1000.0),
            new PlanLine('NO_WEIGHT', 'No weight', 5, 10, null),
        ]);

        self::assertFalse($result->isComplete());
        self::assertSame([], $result->containers);
        self::assertSame(['NO_CAPACITY' => ['capacity'], 'NO_WEIGHT' => ['weight']], $result->incomplete);
    }

    public function testOwnPalletLinesNeverShareAPallet(): void
    {
        $result = $this->planner->plan(PlannerFixtures::pallet(), [PlannerFixtures::ownPallet(30), PlannerFixtures::light(10)]);

        self::assertSame(2, $result->containerCount());
        $own = array_values(array_filter($result->containers, static fn ($container): bool => 'OWN' === $container->dedicatedTo));
        self::assertCount(1, $own);
        self::assertSame(['OWN' => 30], $own[0]->units);
        self::assertSame(75, $own[0]->percent());
    }

    public function testOwnPalletLinesTakeWholePlacesInATruck(): void
    {
        // 50 bags of cement = 2 pallet places (40 + 10), 10 light cases share another place.
        $line = new PlanLine('OWN', 'Cement', 50, 40 * 33, 25000.0, null, 1, true);
        $mixed = new PlanLine('LIGHT', 'Light case', 10, 40 * 33, 10000.0);
        $result = $this->planner->plan(PlannerFixtures::truck(), [$line, $mixed]);

        self::assertSame(1, $result->containerCount());
        $places = $result->containers[0]->slotRatio * 33;
        self::assertEqualsWithDelta(2.25, $places, 1e-9);
    }

    public function testPartialCasesAreSmallerItems(): void
    {
        $line = new PlanLine('WATER', 'Water', 486, 480, 1550.0, null, 6);
        $result = $this->planner->plan(PlannerFixtures::pallet(), [$line]);

        self::assertSame(2, $result->containerCount());
        self::assertSame(480, $result->containers[0]->unitCount());
        self::assertSame(6, $result->containers[1]->unitCount());
    }

    public function testVolumeIsUsedOnlyWhenEveryLineHasDimensions(): void
    {
        $pallet = PlannerFixtures::pallet(1000, 1000.0);
        $withVolume = new PlanLine('A', 'A', 10, 40, 1000.0, 50.0);
        $result = $this->planner->plan($pallet, [$withVolume]);
        self::assertTrue($result->volumeAvailable);
        self::assertEqualsWithDelta(0.5, $result->containers[0]->volumeRatio, 1e-9);
        self::assertSame(BindingConstraint::Volume, $result->containers[0]->binding());

        $result = $this->planner->plan($pallet, [$withVolume, new PlanLine('B', 'B', 1, 40, 1000.0)]);
        self::assertFalse($result->volumeAvailable);
        self::assertNull($result->containers[0]->volumeRatio);
    }

    public function testAnItemHeavierThanTheContainerIsPlacedAloneAndOverloaded(): void
    {
        $line = new PlanLine('ANVIL', 'Anvil', 1, 1, 2000000.0);
        $result = $this->planner->plan(PlannerFixtures::pallet(), [$line]);

        self::assertSame(1, $result->containerCount());
        self::assertTrue($result->containers[0]->isOverloaded());
    }

    public function testAContainerNeedsPlacesAndPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ContainerSpec('X', 'X', 'pallet', 0, 1000);
    }
}
