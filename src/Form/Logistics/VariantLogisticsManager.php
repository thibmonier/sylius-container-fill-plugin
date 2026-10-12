<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Form\Logistics;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\ProductVariantLogisticsInterface;
use Tmonier\SyliusContainerFillPlugin\Entity\VariantContainerCapacityInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ProductVariantLogisticsRepositoryInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\VariantContainerCapacityRepositoryInterface;

/**
 * Loads the logistics tab data and writes it back. The variant form is a live component that
 * submits on every change: submitted data is only staged, and written on the resource
 * controller events "sylius.product_variant.pre_create" / "pre_update", or "sylius.product.*"
 * for simple products whose variant form is embedded (then flushed with the
 * variant).
 */
final class VariantLogisticsManager
{
    /** @var \SplObjectStorage<ProductVariantInterface, VariantLogisticsData> */
    private \SplObjectStorage $staged;

    /**
     * @param FactoryInterface<ProductVariantLogisticsInterface> $logisticsFactory
     * @param FactoryInterface<VariantContainerCapacityInterface> $capacityFactory
     */
    public function __construct(
        private readonly ProductVariantLogisticsRepositoryInterface $logisticsRepository,
        private readonly VariantContainerCapacityRepositoryInterface $capacityRepository,
        private readonly ContainerTypeRepositoryInterface $containerTypeRepository,
        private readonly FactoryInterface $logisticsFactory,
        private readonly FactoryInterface $capacityFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->staged = new \SplObjectStorage();
    }

    public function load(ProductVariantInterface $variant): VariantLogisticsData
    {
        $data = new VariantLogisticsData();
        $logistics = $this->logisticsRepository->findOneByVariant($variant);
        if (null !== $logistics) {
            $data->unitsPerCase = $logistics->getUnitsPerCase();
            $data->caseWeight = $logistics->getCaseWeight();
            $data->stackable = $logistics->isStackable();
            $data->ownPallet = $logistics->isOwnPallet();
            $data->caseMultipleRequired = $logistics->isCaseMultipleRequired();
        }
        foreach ($this->capacityRepository->findByVariant($variant) as $capacity) {
            $data->capacities['t' . (string) $capacity->getContainerType()?->getId()] = [
                'quantity' => $capacity->getQuantity(),
                'unitsPerLayer' => $capacity->getUnitsPerLayer(),
            ];
        }

        return $data;
    }

    public function reset(): void
    {
        $this->staged = new \SplObjectStorage();
    }

    public function stage(ProductVariantInterface $variant, VariantLogisticsData $data): void
    {
        $this->staged[$variant] = $data;
    }

    public function onPreSave(ResourceControllerEvent $event): void
    {
        $subject = $event->getSubject();
        $variants = $subject instanceof ProductInterface ? $subject->getVariants()->toArray() : [$subject];
        foreach ($variants as $variant) {
            if ($variant instanceof ProductVariantInterface && isset($this->staged[$variant])) {
                $this->write($variant, $this->staged[$variant]);
                unset($this->staged[$variant]);
            }
        }
    }

    public function write(ProductVariantInterface $variant, VariantLogisticsData $data): void
    {
        $logistics = $this->logisticsRepository->findOneByVariant($variant);
        if (null === $logistics && !$data->isEmpty()) {
            $logistics = $this->logisticsFactory->createNew();
            $logistics->setVariant($variant);
            $this->entityManager->persist($logistics);
        }
        if (null !== $logistics) {
            $logistics->setUnitsPerCase(max(1, $data->unitsPerCase ?? 1));
            $logistics->setCaseWeight($data->caseWeight);
            $logistics->setStackable($data->stackable);
            $logistics->setOwnPallet($data->ownPallet);
            $logistics->setCaseMultipleRequired($data->caseMultipleRequired);
        }

        $existing = [];
        foreach ($this->capacityRepository->findByVariant($variant) as $capacity) {
            $existing['t' . (string) $capacity->getContainerType()?->getId()] = $capacity;
        }
        foreach ($this->containerTypeRepository->findAllOrdered() as $type) {
            $key = 't' . (string) $type->getId();
            $quantity = $data->capacities[$key]['quantity'] ?? null;
            $capacity = $existing[$key] ?? null;
            if (null === $quantity) {
                if (null !== $capacity) {
                    $this->entityManager->remove($capacity);
                }

                continue;
            }
            if (null === $capacity) {
                $capacity = $this->capacityFactory->createNew();
                $capacity->setVariant($variant);
                $capacity->setContainerType($type);
                $this->entityManager->persist($capacity);
            }
            $capacity->setQuantity($quantity);
            $capacity->setUnitsPerLayer($data->capacities[$key]['unitsPerLayer'] ?? null);
        }
    }
}
