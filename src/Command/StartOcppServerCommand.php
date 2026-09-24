<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Command;

use Aconnect\OcppBundle\Security\BasicAuthenticator;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Aconnect\OcppBundle\Transport\Amp\Ocpp16Acceptor;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\ServerTlsContext;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\WebsocketClientHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use function Amp\trapSignal;

#[AsCommand(name: 'ocpp:server:start', description: 'Start the OCPP 1.6 JSON WebSocket server over TLS')]
final class StartOcppServerCommand extends Command
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $pathPrefix,
        private readonly string $certificate,
        private readonly string $privateKey,
        private readonly ChargePointCredentialVerifier $verifier,
        private readonly WebsocketClientHandler $clientHandler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['TLS certificate' => $this->certificate, 'TLS private key' => $this->privateKey] as $label => $path) {
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException("{$label} must be a readable file: {$path}");
            }
        }

        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('The OpenSSL PHP extension is required to serve WSS.');
        }

        $certificatePath = realpath($this->certificate);
        $privateKeyPath = realpath($this->privateKey);
        if ($certificatePath === false || $privateKeyPath === false) {
            throw new \RuntimeException('The TLS certificate or private key could not be resolved.');
        }

        $certificate = openssl_x509_read('file://'.$certificatePath);
        $privateKey = openssl_pkey_get_private('file://'.$privateKeyPath);
        if ($certificate === false || $privateKey === false || !openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new \RuntimeException('The TLS certificate and private key are invalid or do not match.');
        }

        $logger = new ConsoleLogger($output);
        $server = SocketHttpServer::createForDirectAccess($logger, enableCompression: false, connectionLimitPerIp: 100);
        $tlsContext = (new ServerTlsContext())
            ->withDefaultCertificate(new Certificate($certificatePath, $privateKeyPath));
        $server->expose($this->host.':'.$this->port, (new BindContext())->withTlsContext($tlsContext));

        $acceptor = new Ocpp16Acceptor(new BasicAuthenticator($this->verifier), $this->pathPrefix);
        $endpoint = new Websocket($server, $logger, $acceptor, $this->clientHandler);
        $server->start($endpoint, new DefaultErrorHandler());

        $output->writeln(sprintf('OCPP 1.6 WebSocket server listening at wss://%s:%d%s{chargePointId}', $this->host, $this->port, $this->pathPrefix));
        try {
            trapSignal([SIGINT, SIGTERM]);
        } finally {
            $server->stop();
        }

        return Command::SUCCESS;
    }
}
