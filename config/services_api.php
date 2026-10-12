<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusContainerFillPlugin\Api\OrderLoadPlanProvider;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->set(OrderLoadPlanProvider::class)
        ->args([
            service('sylius.repository.order'),
            service('tmonier_container_fill.cart_fill_checker'),
            service('tmonier_container_fill.repository.order_load_plan'),
            service('sylius_api.context.user.token_based'),
        ])
        ->tag('api_platform.state_provider')
    ;
};
