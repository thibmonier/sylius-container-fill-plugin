<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface;
use Tmonier\SyliusContainerFillPlugin\Repository\ContainerTypeRepositoryInterface;

/** Creates the missing presets (marked indicative, editable). Idempotent. */
final class ContainerTypePresetFixture extends AbstractFixture
{
    /**
     * @param FactoryInterface<ContainerTypeInterface> $factory
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly ObjectManager $manager,
        private readonly FactoryInterface $factory,
        private readonly ContainerTypeRepositoryInterface $repository,
        private readonly RepositoryInterface $localeRepository,
    ) {
    }

    public function getName(): string
    {
        return 'tmonier_container_fill_container_types';
    }

    public function load(array $options): void
    {
        $locales = array_map(static fn (LocaleInterface $locale): string => (string) $locale->getCode(), $this->localeRepository->findAll()) ?: ['en_US'];

        $created = [];
        foreach (ContainerTypePresets::PRESETS as $position => $preset) {
            $type = $this->repository->findOneByCode($preset['code']);
            if ($type instanceof \Tmonier\SyliusContainerFillPlugin\Entity\ContainerTypeInterface) {
                $created[$preset['code']] = $type;

                continue;
            }
            $type = $this->factory->createNew();
            $type->setCode($preset['code']);
            foreach ($locales as $locale) {
                $type->setCurrentLocale($locale);
                $type->setFallbackLocale($locale);
                $type->setName($preset['name']);
            }
            $type->setKind($preset['kind']);
            $type->setInnerLength($preset['length']);
            $type->setInnerWidth($preset['width']);
            $type->setInnerHeight($preset['height']);
            $type->setMaxLoadHeight($preset['loadHeight']);
            $type->setMaxPayload($preset['payload']);
            $type->setSlots($preset['slots']);
            $type->setVolumeEfficiency($preset['efficiency']);
            $type->setIndicative(true);
            $type->setPosition($position);
            $type->setEnabled(true);
            if (null !== $preset['nested'] && isset($created[$preset['nested']])) {
                $type->setNestedType($created[$preset['nested']]);
            }
            $this->manager->persist($type);
            $created[$preset['code']] = $type;
        }

        $this->manager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
    }
}
