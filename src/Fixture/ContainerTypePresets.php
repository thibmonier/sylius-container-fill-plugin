<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Fixture;

/**
 * Indicative presets (millimetres, grams): check them with your carriers. Sources in SPEC.md
 * (EPAL / pallet networks, DFDS and Eurowag for the semi-trailer, container data sheets).
 * Load heights are the height available for goods: total height minus the 144 mm deck.
 */
final class ContainerTypePresets
{
    /** @var list<array{code: string, name: string, kind: string, length: ?int, width: ?int, height: ?int, loadHeight: ?int, payload: int, slots: int, nested: ?string, efficiency: int}> */
    public const PRESETS = [
        ['code' => 'EUR_PALLET', 'name' => 'EUR pallet 1200 x 800', 'kind' => 'pallet', 'length' => 1200, 'width' => 800, 'height' => null, 'loadHeight' => 1656, 'payload' => 1000000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
        ['code' => 'EUR_HALF_PALLET', 'name' => 'Half pallet 800 x 600', 'kind' => 'pallet', 'length' => 800, 'width' => 600, 'height' => null, 'loadHeight' => 1656, 'payload' => 500000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
        ['code' => 'UK_PALLET', 'name' => 'UK / ISO pallet 1200 x 1000', 'kind' => 'pallet', 'length' => 1200, 'width' => 1000, 'height' => null, 'loadHeight' => 1656, 'payload' => 1000000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
        ['code' => 'US_PALLET', 'name' => 'US pallet 48 x 40 in', 'kind' => 'pallet', 'length' => 1219, 'width' => 1016, 'height' => null, 'loadHeight' => 1680, 'payload' => 1000000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
        ['code' => 'SEMI_13_6', 'name' => 'Standard semi-trailer 13.6 m', 'kind' => 'truck', 'length' => 13620, 'width' => 2480, 'height' => 2700, 'loadHeight' => null, 'payload' => 24000000, 'slots' => 33, 'nested' => 'EUR_PALLET', 'efficiency' => 85],
        ['code' => 'MEGA_13_6', 'name' => 'Mega trailer 13.6 m (single deck)', 'kind' => 'truck', 'length' => 13620, 'width' => 2480, 'height' => 2950, 'loadHeight' => null, 'payload' => 24000000, 'slots' => 33, 'nested' => 'EUR_PALLET', 'efficiency' => 85],
        ['code' => 'US_53FT', 'name' => 'US 53 ft trailer', 'kind' => 'truck', 'length' => 16150, 'width' => 2510, 'height' => 2740, 'loadHeight' => null, 'payload' => 20000000, 'slots' => 26, 'nested' => 'US_PALLET', 'efficiency' => 85],
        ['code' => 'CONTAINER_20', 'name' => 'Sea container 20 ft', 'kind' => 'sea_container', 'length' => 5898, 'width' => 2352, 'height' => 2393, 'loadHeight' => null, 'payload' => 28200000, 'slots' => 11, 'nested' => 'EUR_PALLET', 'efficiency' => 85],
        ['code' => 'CONTAINER_40', 'name' => 'Sea container 40 ft', 'kind' => 'sea_container', 'length' => 12032, 'width' => 2352, 'height' => 2393, 'loadHeight' => null, 'payload' => 26700000, 'slots' => 23, 'nested' => 'EUR_PALLET', 'efficiency' => 85],
        ['code' => 'CONTAINER_40HC', 'name' => 'Sea container 40 ft high cube', 'kind' => 'sea_container', 'length' => 12032, 'width' => 2352, 'height' => 2698, 'loadHeight' => null, 'payload' => 26500000, 'slots' => 23, 'nested' => 'EUR_PALLET', 'efficiency' => 85],
        ['code' => 'CARTON_S', 'name' => 'Carton S', 'kind' => 'carton', 'length' => 300, 'width' => 200, 'height' => 150, 'loadHeight' => null, 'payload' => 10000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
        ['code' => 'CARTON_M', 'name' => 'Carton M', 'kind' => 'carton', 'length' => 400, 'width' => 300, 'height' => 300, 'loadHeight' => null, 'payload' => 20000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
        ['code' => 'CARTON_L', 'name' => 'Carton L', 'kind' => 'carton', 'length' => 600, 'width' => 400, 'height' => 400, 'loadHeight' => null, 'payload' => 30000, 'slots' => 1, 'nested' => null, 'efficiency' => 90],
    ];
}
