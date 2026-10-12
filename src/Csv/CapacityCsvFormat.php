<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Csv;

/**
 * variant_code;container_type;capacity;units_per_layer;units_per_case;case_weight_kg;layer_height_mm
 * (layer_height_mm since 1.1, optional: files of 1.0 import unchanged)
 * The first two and capacity are required; an empty capacity removes it. Empty optional
 * columns leave the stored value unchanged. Delimiter ";" or "," (from the header), UTF-8
 * with or without BOM.
 */
final class CapacityCsvFormat
{
    public const COLUMNS = ['variant_code', 'container_type', 'capacity', 'units_per_layer', 'units_per_case', 'case_weight_kg', 'layer_height_mm'];

    public const REQUIRED = ['variant_code', 'container_type', 'capacity'];
}
