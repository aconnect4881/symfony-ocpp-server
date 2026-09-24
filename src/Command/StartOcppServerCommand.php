<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Command;

use Aconnect\OcppBundle\Security\BasicAuthenticator;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Aconnect\OcppBundle\Protocol\V16\CallHandler;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Aconnect\OcppBundle\Transport\Amp\Ocpp16ClientHandler;
use Aconnect\OcppBundle\Transport\Amp\Ocpp16Acceptor;
use Aconnect\OcppBundle\Transport\Amp\ConnectionObserver;
use Aconnect\OcppBundle\Transport\Amp\ClientRegistry;
use Aconnect\OcppBundle\Transport\Amp\OutboundCallSender;
use Aconnect\OcppBundle\Transport\Messenger\MessengerPump;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\ServerTlsContext;
use Amp\Websocket\Server\Websocket;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Revolt\EventLoop;
use function Amp\trapSignal;

#[AsCommand(name: 'ocpp:server:start', description: 'Start the OCPP 1.6 JSON WebSocket server')]
final class StartOcppServerCommand extends Command
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $pathPrefix,
        private readonly ?string $certificate,
        private readonly ?string $privateKey,
        private readonly ChargePointCredentialVerifier $verifier,
        private readonly CallHandler $actionHandler,
        private readonly string $transport = 'direct_tls',
        private readonly array $trustedProxies = [],
        private readonly ?ConnectionObserver $connectionObserver = null,
        private readonly ?ClientRegistry $clients = null,
        private readonly ?OutboundCallSender $outbound = null,
        private readonly ?TransportInterface $messengerTransport = null,
        private readonly ?MessageBusInterface $messageBus = null,
        private readonly ?string $messengerTransportName = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        $server = SocketHttpServer::createForDirectAccess($logger, enableCompression: false, connectionLimitPerIp: 100);
        if ($this->transport === 'direct_tls') {
            $context = (new BindContext())->withTlsContext($this->tlsContext());
        } elseif ($this->transport === 'trusted_proxy' && $this->trustedProxies !== []) {
            $context = new BindContext();
        } elseif ($this->transport !== 'trusted_proxy') {
            throw new \RuntimeException('Unknown OCPP transport mode.');
        } else {
            throw new \RuntimeException('Trusted proxy transport requires at least one trusted proxy address.');
        }

        $port = $this->port;
        if (($assignedPort = getenv('PORT')) !== false) {
            if (!ctype_digit($assignedPort) || (int) $assignedPort < 1 || (int) $assignedPort > 65535) {
                throw new \RuntimeException('PORT must be an integer between 1 and 65535.');
            }
            $port = (int) $assignedPort;
        }

        $server->expose($this->host.':'.$port, $context);

        $acceptor = new Ocpp16Acceptor(new BasicAuthenticator($this->verifier), $this->pathPrefix, null, $this->transport === 'trusted_proxy' ? $this->trustedProxies : []);
        $clients = $this->clients ?? new ClientRegistry();
        $outbound = $this->outbound ?? new OutboundCallSender($clients, new FrameCodec());
        $endpoint = new Websocket($server, $logger, $acceptor, new Ocpp16ClientHandler($this->actionHandler, new FrameCodec(), $this->pathPrefix, $logger, $this->connectionObserver, $clients, $outbound));
        $server->start($endpoint, new DefaultErrorHandler());

        $pumpId = null;
        if ($this->messengerTransport !== null && $this->messageBus !== null && $this->messengerTransportName !== null) {
            $pumpId = (new MessengerPump($this->messengerTransport, $this->messageBus, $this->messengerTransportName, $logger))->start();
        }

        $output->writeln(sprintf('OCPP 1.6 WebSocket server listening at %s://%s:%d%s{chargePointId}', $this->transport === 'direct_tls' ? 'wss' : 'ws', $this->host, $port, $this->pathPrefix));
        try {
            trapSignal([SIGINT, SIGTERM]);
        } finally {
            if ($pumpId !== null) {
                EventLoop::cancel($pumpId);
            }
            $server->stop();
        }

        return Command::SUCCESS;
    }

    private function tlsContext(): ServerTlsContext
    {
        foreach (['TLS certificate' => $this->certificate, 'TLS private key' => $this->privateKey] as $label => $path) {
            if ($path === null || !is_file($path) || !is_readable($path)) {
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

        return (new ServerTlsContext())->withDefaultCertificate(new Certificate($certificatePath, $privateKeyPath));
    }
}
