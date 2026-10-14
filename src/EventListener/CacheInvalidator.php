<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeTranslationInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Rule\CartFillChecker;

/** Plans of the request are computed again once logistics data, rules or variants change. */
final readonly class CacheInvalidator
{
    private const CLASSES = [
        ContainerRuleInterface::class,
        ContainerTypeInterface::class,
        ContainerTypeTranslationInterface::class,
        ProductVariantLogisticsInterface::class,
        VariantContainerCapacityInterface::class,
        ProductVariantInterface::class,
    ];

    public function __construct(private CartFillChecker $checker)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $entities = array_merge(
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        );
        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $entities[] = $collection->getOwner();
        }

        foreach ($entities as $entity) {
            foreach (self::CLASSES as $class) {
                if ($entity instanceof $class) {
                    $this->checker->invalidate();

                    return;
                }
            }
        }
    }
}
