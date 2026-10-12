<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Tmonier\SyliusContainerFillPlugin\Api\OrderLoadPlanProvider;

/**
 * Read-only GET /api/v2/shop/orders/{tokenValue}/load-plan: the plan computed now for a cart,
 * the snapshot written at checkout for a placed order. A separate resource rather than a
 * property of the core Order (no change to the core normalization, no conflict with other
 * plugins decorating it).
 */
#[ApiResource(
    shortName: 'ContainerFillLoadPlan',
    operations: [
        new Get(
            uriTemplate: '/shop/orders/{tokenValue}/load-plan',
            uriVariables: ['tokenValue'],
            description: 'Load plan (containers, fill ratios, binding constraint, rule violations and suggestion) of a cart or of a placed order. Indicative.',
            provider: OrderLoadPlanProvider::class,
            name: 'tmonier_container_fill_shop_order_load_plan',
        ),
    ],
)]
final class OrderLoadPlan
{
    /**
     * @param array<mixed> $rules every applicable rule, checked on all the lines of its scope
     * @param array<mixed> $lineViolations
     * @param array<mixed> $plan the load plan: each line planned once, by its most specific rule
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $tokenValue,
        /** "cart" (computed on read) or "snapshot" (recorded at checkout), "none" for a placed order without plan. */
        public readonly string $source,
        public readonly bool $satisfied,
        public readonly array $rules = [],
        public readonly array $lineViolations = [],
        public readonly ?string $overriddenBy = null,
        public readonly bool $indicative = true,
        public readonly array $plan = [],
        public readonly int $containerCount = 0,
    ) {
    }
}
