<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;

/** @extends EntityRepository<VariantContainerCapacityInterface> */
class VariantContainerCapacityRepository extends EntityRepository implements VariantContainerCapacityRepositoryInterface
{
    public function findByVariantIds(array $variantIds): array
    {
        /** @var list<VariantContainerCapacityInterface> $capacities */
        $capacities = $this->createQueryBuilder('o')
            ->addSelect('containerType')
            ->innerJoin('o.containerType', 'containerType')
            ->andWhere('IDENTITY(o.variant) IN (:ids)')
            ->setParameter('ids', $variantIds)
            ->getQuery()
            ->getResult()
        ;

        return $capacities;
    }

    public function findByVariant(ProductVariantInterface $variant): array
    {
        return null === $variant->getId() ? [] : $this->findByVariantIds([(int) $variant->getId()]);
    }

    public function findOneByVariantAndType(ProductVariantInterface $variant, ContainerTypeInterface $type): ?VariantContainerCapacityInterface
    {
        $capacity = $this->findOneBy(['variant' => $variant, 'containerType' => $type]);

        return $capacity instanceof VariantContainerCapacityInterface ? $capacity : null;
    }

    public function findAllForExport(): array
    {
        /** @var list<VariantContainerCapacityInterface> $capacities */
        $capacities = $this->createQueryBuilder('o')
            ->addSelect('variant', 'containerType')
            ->innerJoin('o.variant', 'variant')
            ->innerJoin('o.containerType', 'containerType')
            ->addOrderBy('variant.code', 'ASC')
            ->addOrderBy('containerType.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $capacities;
    }
}
