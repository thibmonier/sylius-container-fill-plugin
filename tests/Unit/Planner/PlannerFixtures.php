<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\Planner;

use Tmonier\SyliusContainerFillPlugin\Planner\ContainerSpec;
use Tmonier\SyliusContainerFillPlugin\Planner\PlanLine;

/** Reference containers and lines (grams, mm³). */
final class PlannerFixtures
{
    public static function pallet(int $payloadKg = 1000, ?float $volume = null): ContainerSpec
    {
        return new ContainerSpec('EUR_PALLET', 'EUR pallet', 'pallet', 1, $payloadKg * 1000, $volume);
    }

    public static function truck(int $places = 33, int $payloadKg = 24000): ContainerSpec
    {
        return new ContainerSpec('SEMI', 'Semi-trailer', 'truck', $places, $payloadKg * 1000);
    }

    /** 40 cases fill a pallet, 10 kg each: places bind first. */
    public static function light(int $quantity, int $capacity = 40): PlanLine
    {
        return new PlanLine('LIGHT', 'Light case', $quantity, $capacity, 10000.0);
    }

    /** 48 boxes by places but 22 kg each: 45 boxes reach 990 kg, weight binds first. */
    public static function heavy(int $quantity, int $capacity = 48): PlanLine
    {
        return new PlanLine('HEAVY', 'Heavy box', $quantity, $capacity, 22000.0);
    }

    public static function ownPallet(int $quantity, int $capacity = 40): PlanLine
    {
        return new PlanLine('OWN', 'Cement', $quantity, $capacity, 25000.0, null, 1, true);
    }
}
