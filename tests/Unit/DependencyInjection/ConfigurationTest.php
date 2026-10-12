<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Tmonier\SyliusContainerFillPlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(['length' => 'cm', 'weight' => 'kg'], $config['units']);
        self::assertSame('ROLE_TMONIER_CONTAINER_FILL_OVERRIDE', $config['override_role']);
        self::assertTrue($config['workflow_guard']);
        self::assertTrue($config['cart_gauge']);
        self::assertTrue($config['product_preview']);
    }

    public function testUnknownUnitsAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['units' => ['length' => 'furlong']]]);
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
