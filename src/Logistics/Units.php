<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Logistics;

/**
 * Units of the Sylius variant fields (width, height, depth, weight have none in Sylius): set in
 * the plugin configuration. The plugin itself stores millimetres and grams.
 */
final class Units
{
    public const LENGTH_UNITS = ['mm' => 1.0, 'cm' => 10.0, 'm' => 1000.0, 'in' => 25.4];

    public const WEIGHT_UNITS = ['g' => 1.0, 'kg' => 1000.0, 'lb' => 453.59237];

    public function __construct(
        public readonly string $length = 'cm',
        public readonly string $weight = 'kg',
    ) {
        if (!isset(self::LENGTH_UNITS[$length]) || !isset(self::WEIGHT_UNITS[$weight])) {
            throw new \InvalidArgumentException(sprintf('Unknown units "%s" / "%s".', $length, $weight));
        }
    }

    public function toMillimetres(float $value): float
    {
        return $value * self::LENGTH_UNITS[$this->length];
    }

    public function toGrams(float $value): float
    {
        return $value * self::WEIGHT_UNITS[$this->weight];
    }
}
