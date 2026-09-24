<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Command;

use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Amp\Websocket\Server\WebsocketClientHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class StartOcppServerCommandTest extends TestCase
{
    public function testRefusesMissingTlsFilesBeforeOpeningListener(): void
    {
        $command = new StartOcppServerCommand(
            '127.0.0.1',
            9000,
            '/ocpp/',
            '/missing/ocpp-certificate.pem',
            '/missing/ocpp-key.pem',
            $this->createMock(ChargePointCredentialVerifier::class),
            $this->createMock(WebsocketClientHandler::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TLS certificate must be a readable file');
        (new CommandTester($command))->execute([]);
    }
}
