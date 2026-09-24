<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Transport\Messenger;

use Aconnect\OcppBundle\Transport\Messenger\MessengerPump;
use Amp\DeferredFuture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessengerPumpTest extends TestCase
{
    public function testDispatchesReceivedEnvelopeAndAcknowledgesIt(): void
    {
        $envelope = new Envelope(new \stdClass());
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('get')->willReturn([$envelope]);
        $transport->expects(self::once())->method('ack')->with($envelope);
        $transport->expects(self::never())->method('reject');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(function (Envelope $received): bool {
            return $received->last(ReceivedStamp::class)?->getTransportName() === 'ocpp';
        }))->willReturn($envelope);

        (new MessengerPump($transport, $bus, 'ocpp', new NullLogger()))->consume();
    }

    public function testRejectsMessageWhenTheAppHandlerFails(): void
    {
        $envelope = new Envelope(new \stdClass());
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('get')->willReturn([$envelope]);
        $transport->expects(self::never())->method('ack');
        $transport->expects(self::once())->method('reject')->with($envelope);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Handler failed'));

        (new MessengerPump($transport, $bus, 'ocpp', new NullLogger()))->consume();
    }

    public function testScheduledDispatchCanAwaitAChargerResponseWithoutBlockingTheLoop(): void
    {
        $envelope = new Envelope(new \stdClass());
        $gate = new DeferredFuture();
        $getCalls = 0;
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('get')->willReturnCallback(function () use (&$getCalls, $envelope): array {
            return ++$getCalls === 1 ? [$envelope] : [];
        });
        $transport->expects(self::once())->method('ack')->with($envelope);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function () use ($gate, $envelope): Envelope {
            $gate->getFuture()->await();

            return $envelope;
        });

        $watcher = (new MessengerPump($transport, $bus, 'ocpp', new NullLogger()))->start(0.001);
        try {
            EventLoop::delay(0.01, static function () use ($gate): void {
                $gate->complete();
            });
            \Amp\delay(0.04);
        } finally {
            EventLoop::cancel($watcher);
        }
    }
}
