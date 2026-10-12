<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Logistics;

/** Resource identifiers are typed mixed by Sylius: the integer ids of the Doctrine entities. */
final class EntityId
{
    public static function int(mixed $id): int
    {
        return \is_int($id) ? $id : (is_numeric($id) ? (int) $id : 0);
    }

    public static function string(mixed $id): string
    {
        return \is_scalar($id) ? (string) $id : '';
    }
}
