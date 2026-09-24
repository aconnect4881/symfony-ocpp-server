<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests;

use Aconnect\OcppBundle\AconnectOcppBundle;
use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use Aconnect\OcppBundle\Protocol\V16\NotSupportedCallHandler;
use Aconnect\OcppBundle\Security\RejectAllChargePointCredentialVerifier;
use Aconnect\OcppBundle\Transport\Amp\ClientRegistry;
use Aconnect\OcppBundle\Transport\Amp\OutboundCallSender;
use Symfony\Component\DependencyInjection\Reference;
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
            'credential_verifier_service' => 'app.test_credential_verifier',
            'action_handler_service' => 'app.test_action_handler',
            'connection_observer_service' => 'app.test_connection_observer',
            'tls' => ['certificate' => '/cert.pem', 'private_key' => '/key.pem'],
        ]], $container);

        $command = $container->getDefinition(StartOcppServerCommand::class);
        self::assertSame('0.0.0.0', $command->getArgument('$host'));
        self::assertSame(8443, $command->getArgument('$port'));
        self::assertSame('/ocpp/', $command->getArgument('$pathPrefix'));
        self::assertSame('/cert.pem', $command->getArgument('$certificate'));
        self::assertSame('/key.pem', $command->getArgument('$privateKey'));
        self::assertSame('direct_tls', $command->getArgument('$transport'));
        self::assertSame([], $command->getArgument('$trustedProxies'));
        self::assertEquals(new Reference('app.test_credential_verifier'), $command->getArgument('$verifier'));
        self::assertEquals(new Reference('app.test_action_handler'), $command->getArgument('$actionHandler'));
        self::assertEquals(new Reference('app.test_connection_observer'), $command->getArgument('$connectionObserver'));
        self::assertTrue($command->hasTag('console.command'));
        self::assertEquals(new Reference(ClientRegistry::class), $command->getArgument('$clients'));
        self::assertEquals(new Reference(OutboundCallSender::class), $command->getArgument('$outbound'));
    }

    public function testProxyConfigurationNeedsNoLocalCertificate(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        (new AconnectOcppBundle())->getContainerExtension()->load([[
            'transport' => 'trusted_proxy',
            'trusted_proxies' => ['127.0.0.1'],
        ]], $container);

        $command = $container->getDefinition(StartOcppServerCommand::class);
        self::assertSame('trusted_proxy', $command->getArgument('$transport'));
        self::assertSame(['127.0.0.1'], $command->getArgument('$trustedProxies'));
        self::assertNull($command->getArgument('$certificate'));
        self::assertNull($command->getArgument('$privateKey'));
        self::assertNull($command->getArgument('$connectionObserver'));
        self::assertNull($command->getArgument('$messengerTransport'));
        self::assertNull($command->getArgument('$messageBus'));
    }

    public function testInstallationDefaultsToRegisteredFailClosedServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        (new AconnectOcppBundle())->getContainerExtension()->load([[]], $container);

        self::assertTrue($container->hasDefinition(RejectAllChargePointCredentialVerifier::class));
        self::assertTrue($container->hasDefinition(NotSupportedCallHandler::class));
        $command = $container->getDefinition(StartOcppServerCommand::class);
        self::assertEquals(new Reference(RejectAllChargePointCredentialVerifier::class), $command->getArgument('$verifier'));
        self::assertEquals(new Reference(NotSupportedCallHandler::class), $command->getArgument('$actionHandler'));
        self::assertFalse((new RejectAllChargePointCredentialVerifier())->verify('CP-01', 'some-secret'));
    }

    public function testMessengerTransportReferencesAreOnlyAddedWhenConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        (new AconnectOcppBundle())->getContainerExtension()->load([[
            'messenger_transport' => 'ocpp',
            'messenger_bus_service' => 'messenger.bus.default',
        ]], $container);

        $command = $container->getDefinition(StartOcppServerCommand::class);
        self::assertEquals(new Reference('messenger.transport.ocpp'), $command->getArgument('$messengerTransport'));
        self::assertEquals(new Reference('messenger.bus.default'), $command->getArgument('$messageBus'));
        self::assertSame('ocpp', $command->getArgument('$messengerTransportName'));
    }
}
