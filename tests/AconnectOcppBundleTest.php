<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests;

use Aconnect\OcppBundle\AconnectOcppBundle;
use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AconnectOcppBundleTest extends TestCase
{
    public function testConfigurationRegistersStartCommandWithTlsAndListenerSettings(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        (new AconnectOcppBundle())->getContainerExtension()->load([[
            'host' => '0.0.0.0',
            'port' => 8443,
            'tls' => ['certificate' => '/cert.pem', 'private_key' => '/key.pem'],
        ]], $container);

        $command = $container->getDefinition(StartOcppServerCommand::class);
        self::assertSame('0.0.0.0', $command->getArgument('$host'));
        self::assertSame(8443, $command->getArgument('$port'));
        self::assertSame('/ocpp/', $command->getArgument('$pathPrefix'));
        self::assertSame('/cert.pem', $command->getArgument('$certificate'));
        self::assertSame('/key.pem', $command->getArgument('$privateKey'));
        self::assertTrue($command->hasTag('console.command'));
    }
}
