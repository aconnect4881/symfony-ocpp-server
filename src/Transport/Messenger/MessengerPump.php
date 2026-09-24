<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Messenger;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/** Polls a dedicated Messenger transport in the WebSocket server process. */
final readonly class MessengerPump
{
    public function __construct(
        private TransportInterface $transport,
        private MessageBusInterface $bus,
        private string $transportName,
        private LoggerInterface $logger,
    ) {
    }

    public function start(float $intervalSeconds = 0.25): string
    {
        if ($intervalSeconds <= 0) {
            throw new \InvalidArgumentException('Messenger poll interval must be positive.');
        }

        return EventLoop::repeat($intervalSeconds, $this->consume(...));
    }

    public function consume(): void
    {
        try {
            foreach ($this->transport->get() as $envelope) {
                try {
                    $this->bus->dispatch($envelope->with(new ReceivedStamp($this->transportName)));
                    $this->transport->ack($envelope);
                } catch (\Throwable $exception) {
                    $this->logger->error('OCPP Messenger dispatch failed', ['exception' => $exception]);
                    try {
                        $this->transport->reject($envelope);
                    } catch (\Throwable $rejectException) {
                        $this->logger->error('OCPP Messenger reject failed', ['exception' => $rejectException]);
                    }
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('OCPP Messenger receive failed', ['exception' => $exception]);
        }
    }
}
