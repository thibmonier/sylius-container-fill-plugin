<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;

/** @extends RepositoryInterface<ContainerTypeInterface> */
interface ContainerTypeRepositoryInterface extends RepositoryInterface
{
    /** @return list<ContainerTypeInterface> by position */
    public function findAllOrdered(): array;

    /** @return list<ContainerTypeInterface> enabled types, by position */
    public function findEnabled(): array;

    public function findOneByCode(string $code): ?ContainerTypeInterface;

    public function createListQueryBuilder(string $localeCode): QueryBuilder;
}
