<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;

/** @extends EntityRepository<ProductVariantLogisticsInterface> */
class ProductVariantLogisticsRepository extends EntityRepository implements ProductVariantLogisticsRepositoryInterface
{
    public function findOneByVariant(ProductVariantInterface $variant): ?ProductVariantLogisticsInterface
    {
        if (null === $variant->getId()) {
            return null;
        }
        $logistics = $this->findOneBy(['variant' => $variant]);

        return $logistics instanceof ProductVariantLogisticsInterface ? $logistics : null;
    }

    public function findByVariantIds(array $variantIds): array
    {
        /** @var list<ProductVariantLogisticsInterface> $rows */
        $rows = $this->createQueryBuilder('o')
            ->andWhere('IDENTITY(o.variant) IN (:ids)')
            ->setParameter('ids', $variantIds)
            ->getQuery()
            ->getResult()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->getVariant()?->getId()] = $row;
        }

        return $result;
    }
}
