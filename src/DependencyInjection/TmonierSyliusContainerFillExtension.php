<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class TmonierSyliusContainerFillExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{units: array{length: string, weight: string}, override_role: ?string, workflow_guard: bool, cart_gauge: bool, product_preview: bool, driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_container_fill', $config['driver'], $config['resources'], $container);

        $container->setParameter('tmonier_container_fill.units.length', $config['units']['length']);
        $container->setParameter('tmonier_container_fill.units.weight', $config['units']['weight']);
        foreach (['override_role', 'workflow_guard', 'cart_gauge', 'product_preview'] as $key) {
            $container->setParameter('tmonier_container_fill.' . $key, $config[$key]);
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
        if ($config['workflow_guard']) {
            $container->getDefinition('tmonier_container_fill.checkout_complete_guard')
                ->addTag('kernel.event_listener', ['event' => 'workflow.sylius_order_checkout.guard.complete']);
        }
        if (self::hasApiPlatform($container)) {
            $loader->load('services_api.php');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Grids are processed before this extension is loaded: the model class parameters have
        // to exist at prepend time.
        $configs = array_map(
            static fn (array $config): array => isset($config['resources']) ? ['resources' => $config['resources']] : [],
            $container->getExtensionConfig($this->getAlias()),
        );
        /** @var array{resources: array<string, array{classes: array{model: string}}>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        foreach ($config['resources'] as $name => $resource) {
            $container->setParameter(sprintf('tmonier_container_fill.model.%s.class', $name), $resource['classes']['model']);
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusContainerFillPlugin' => [
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Tmonier\SyliusContainerFillPlugin\Entity',
                        'is_bundle' => true,
                    ],
                ],
            ],
        ]);

        if (self::hasApiPlatform($container)) {
            $container->prependExtensionConfig('api_platform', [
                'mapping' => ['paths' => [\dirname(__DIR__) . '/Api/Resource']],
            ]);
        }

        $this->prependDoctrineMigrations($container);
    }

    private static function hasApiPlatform(ContainerBuilder $container): bool
    {
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : null;

        return \is_array($bundles) && isset($bundles['ApiPlatformBundle']) && interface_exists(\ApiPlatform\State\ProviderInterface::class);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_container_fill';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusContainerFillPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusContainerFillPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
