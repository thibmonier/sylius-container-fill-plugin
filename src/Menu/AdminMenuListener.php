<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');
        if (!$configuration instanceof \Knp\Menu\ItemInterface) {
            return;
        }

        $configuration
            ->addChild('tmonier_container_types', ['route' => 'tmonier_container_fill_admin_container_type_index'])
            ->setLabel('tmonier_container_fill.ui.container_types')
            ->setLabelAttribute('icon', 'tabler:truck-loading')
        ;
        $configuration
            ->addChild('tmonier_container_rules', ['route' => 'tmonier_container_fill_admin_container_rule_index'])
            ->setLabel('tmonier_container_fill.ui.container_rules')
            ->setLabelAttribute('icon', 'tabler:packages')
        ;
    }
}
