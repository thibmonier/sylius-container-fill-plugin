<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillCheckerInterface;

/**
 * Every test runs in a transaction rolled back at the end (the kernel is not rebooted between
 * requests of a functional test). Fixtures are random: tests create what they need, and the
 * rules of the demo fixtures are disabled inside the transaction.
 */
trait ContainerFillTestTrait
{
    private TestCatalog $catalog;

    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
        $this->catalog = new TestCatalog(self::getContainer());
        $this->catalog->disableOtherRules();
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    private function checker(): CartFillCheckerInterface
    {
        $checker = self::getContainer()->get('tmonier_container_fill.cart_fill_checker');
        self::assertInstanceOf(CartFillCheckerInterface::class, $checker);

        return $checker;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
