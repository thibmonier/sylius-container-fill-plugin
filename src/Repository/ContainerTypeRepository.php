<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;

/** @extends EntityRepository<ContainerTypeInterface> */
class ContainerTypeRepository extends EntityRepository implements ContainerTypeRepositoryInterface
{
    public function findAllOrdered(): array
    {
        /** @var list<ContainerTypeInterface> $types */
        $types = $this->createQueryBuilder('o')->addOrderBy('o.position', 'ASC')->addOrderBy('o.id', 'ASC')->getQuery()->getResult();

        return $types;
    }

    public function findEnabled(): array
    {
        /** @var list<ContainerTypeInterface> $types */
        $types = $this->createQueryBuilder('o')
            ->andWhere('o.enabled = true')
            ->addOrderBy('o.position', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $types;
    }

    public function findOneByCode(string $code): ?ContainerTypeInterface
    {
        $type = $this->findOneBy(['code' => $code]);

        return $type instanceof ContainerTypeInterface ? $type : null;
    }

    public function createListQueryBuilder(string $localeCode): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->leftJoin('o.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->setParameter('localeCode', $localeCode)
        ;
    }
}
