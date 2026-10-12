<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusContainerFillPlugin\Functional;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Tmonier\SyliusContainerFillPlugin\ContainerFillTestTrait;

trait FunctionalTestTrait
{
    use ContainerFillTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    private function loginShopUser(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        self::assertInstanceOf(ShopUserInterface::class, $user);
        $this->client->loginUser($user, 'shop');
    }

    private function loginAdmin(): AdminUserInterface
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');

        return $admin;
    }

    private function url(string $path): string
    {
        return '/' . $this->catalog->channel()->getDefaultLocale()?->getCode() . $path;
    }
}
