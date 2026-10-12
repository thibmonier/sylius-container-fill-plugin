<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmonier\SyliusContainerFillPlugin\DependencyInjection\Compiler\QuickOrderBridgePass;

final class TmonierSyliusContainerFillPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new QuickOrderBridgePass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
