<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Sylius\Component\Core\Model\OrderInterface;

/** Plans the cart for every applicable rule (computed on read, cached for the request). */
interface CartFillCheckerInterface
{
    public function check(OrderInterface $order): CartFillReport;
}
