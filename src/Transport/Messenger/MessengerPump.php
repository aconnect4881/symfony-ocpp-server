<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Messenger;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use function Amp\async;

/** Polls a dedicated Messenger transport in the WebSocket server process. */
final class MessengerPump
{
    private bool $busy = false;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly MessageBusInterface $bus,
        private readonly string $transportName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function start(float $intervalSeconds = 0.25): string
    {
        if ($intervalSeconds <= 0) {
            throw new \InvalidArgumentException('Messenger poll interval must be positive.');
        }

        return EventLoop::repeat($intervalSeconds, function (): void {
            if ($this->busy) {
                return;
            }

            $this->busy = true;
            async(function (): void {
                try {
                    $this->consume();
                } finally {
                    $this->busy = false;
                }
            });
        });
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
