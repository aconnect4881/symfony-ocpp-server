<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Transport\Messenger;

use Aconnect\OcppBundle\Transport\Messenger\MessengerPump;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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
}
