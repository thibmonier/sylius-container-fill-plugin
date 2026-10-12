<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/**
 * Splits lines into containers of one type. Implementations must be pure (no I/O): the plan is
 * computed again on every read of the cart.
 */
interface LoadPlannerInterface
{
    /** @param list<PlanLine> $lines */
    public function plan(ContainerSpec $container, array $lines): FillResult;
}
