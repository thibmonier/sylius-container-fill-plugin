<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Planner;

/** The dimension that fills a container first. */
enum BindingConstraint: string
{
    case Slots = 'slots';
    case Weight = 'weight';
    case Volume = 'volume';
}
