<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Command;

use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use Aconnect\OcppBundle\Protocol\V16\CallHandler;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class StartOcppServerCommandTest extends TestCase
{
    public function testRefusesMissingTlsFilesBeforeOpeningListener(): void
    {
        $command = new StartOcppServerCommand(
            '127.0.0.1',
            8081,
            '/ocpp/',
            '/missing/ocpp-certificate.pem',
            '/missing/ocpp-key.pem',
            $this->createMock(ChargePointCredentialVerifier::class),
            $this->createMock(CallHandler::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TLS certificate must be a readable file');
        (new CommandTester($command))->execute([]);
    }

    public function testProxyModeRequiresAnExplicitTrustedAddress(): void
    {
        $command = new StartOcppServerCommand(
            '127.0.0.1',
            8081,
            '/ocpp/',
            null,
            null,
            $this->createMock(ChargePointCredentialVerifier::class),
            $this->createMock(CallHandler::class),
            'trusted_proxy',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Trusted proxy transport requires at least one trusted proxy address.');
        (new CommandTester($command))->execute([]);
    }
}
