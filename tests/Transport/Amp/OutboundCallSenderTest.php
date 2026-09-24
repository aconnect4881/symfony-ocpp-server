<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Transport\Amp;

use Aconnect\OcppBundle\Protocol\V16\Call;
use Aconnect\OcppBundle\Protocol\V16\CallError;
use Aconnect\OcppBundle\Protocol\V16\CallResult;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Aconnect\OcppBundle\Transport\Amp\ClientRegistry;
use Aconnect\OcppBundle\Transport\Amp\OutboundCallSender;
use Amp\Websocket\WebsocketClient;
use PHPUnit\Framework\TestCase;

final class OutboundCallSenderTest extends TestCase
{
    public function testMatchesResponseOnlyToTheRightConnectionAndUniqueId(): void
    {
        $clients = new ClientRegistry();
        $codec = new FrameCodec();
        $call = null;
        $client = $this->createMock(WebsocketClient::class);
        $client->expects(self::once())->method('sendText')->willReturnCallback(function (string $frame) use ($codec, &$call): void {
            $call = $codec->decode($frame);
        });
        $clients->register('CP-01', $client);
        $sender = new OutboundCallSender($clients, $codec);

        $future = $sender->sendCall('CP-01', 'Reset', (object) ['type' => 'Soft']);
        self::assertInstanceOf(Call::class, $call);
        self::assertSame('Reset', $call->action);
        self::assertFalse($sender->resolve('CP-01', $this->createMock(WebsocketClient::class), new CallResult($call->uniqueId, new \stdClass())));
        self::assertFalse($sender->resolve('CP-02', $client, new CallResult($call->uniqueId, new \stdClass())));
        self::assertFalse($sender->resolve('CP-01', $client, new CallResult('different-id', new \stdClass())));
        self::assertTrue($sender->resolve('CP-01', $client, new CallError($call->uniqueId, 'NotSupported', 'No reset', new \stdClass())));
        self::assertInstanceOf(CallError::class, $future->await());
        self::assertFalse($sender->resolve('CP-01', $client, new CallResult($call->uniqueId, new \stdClass())));
    }

    public function testDisconnectFailsPendingCallAndLeavesReplacementRegistered(): void
    {
        $clients = new ClientRegistry();
        $old = $this->createMock(WebsocketClient::class);
        $new = $this->createMock(WebsocketClient::class);
        $clients->register('CP-01', $old);
        $sender = new OutboundCallSender($clients, new FrameCodec());
        $future = $sender->sendCall('CP-01', 'GetConfiguration', new \stdClass());

        self::assertSame($old, $clients->register('CP-01', $new));
        $sender->disconnect('CP-01', $old);
        self::assertFalse($clients->unregister('CP-01', $old));
        self::assertSame($new, $clients->get('CP-01'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disconnected');
        $future->await();
    }

    public function testUnansweredCallExpires(): void
    {
        $clients = new ClientRegistry();
        $clients->register('CP-01', $this->createMock(WebsocketClient::class));
        $future = (new OutboundCallSender($clients, new FrameCodec()))->sendCall('CP-01', 'Heartbeat', new \stdClass(), 0.01);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timed out');
        $future->await();
    }
}
