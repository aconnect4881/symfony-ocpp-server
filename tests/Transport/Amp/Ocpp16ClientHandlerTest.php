<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Transport\Amp;

use Aconnect\OcppBundle\Protocol\V16\Call;
use Aconnect\OcppBundle\Protocol\V16\CallError;
use Aconnect\OcppBundle\Protocol\V16\CallHandler;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Aconnect\OcppBundle\Protocol\V16\InvalidFrameException;
use Aconnect\OcppBundle\Transport\Amp\Ocpp16ClientHandler;
use Aconnect\OcppBundle\Transport\Amp\ClientRegistry;
use Aconnect\OcppBundle\Transport\Amp\OutboundCallSender;
use Amp\Websocket\WebsocketClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class Ocpp16ClientHandlerTest extends TestCase
{
    public function testDecodesCallDispatchesIdentityAndEncodesResponse(): void
    {
        $handler = $this->createMock(CallHandler::class);
        $handler->expects(self::once())->method('handle')
            ->with('CP-01', self::callback(fn (Call $call): bool => $call->action === 'Heartbeat' && $call->uniqueId === 'abc'))
            ->willReturn((object) ['currentTime' => '2026-09-24T00:00:00Z']);

        $transport = new Ocpp16ClientHandler($handler, new FrameCodec(), '/ocpp/', new NullLogger());
        self::assertSame('[3,"abc",{"currentTime":"2026-09-24T00:00:00Z"}]', $transport->respond('CP-01', '[2,"abc","Heartbeat",{}]'));
    }

    public function testErrorAlwaysUsesIncomingCallId(): void
    {
        $handler = $this->createMock(CallHandler::class);
        $handler->method('handle')->willReturn(new CallError('wrong', 'NotImplemented', 'No handler', new \stdClass()));

        $transport = new Ocpp16ClientHandler($handler, new FrameCodec(), '/ocpp/', new NullLogger());
        self::assertSame('[4,"abc","NotImplemented","No handler",{}]', $transport->respond('CP-01', '[2,"abc","Custom",{}]'));
    }

    public function testRejectsUnsolicitedResponse(): void
    {
        $transport = new Ocpp16ClientHandler($this->createMock(CallHandler::class), new FrameCodec(), '/ocpp/', new NullLogger());
        $this->expectException(InvalidFrameException::class);
        $transport->respond('CP-01', '[3,"unknown",{}]');
    }

    public function testCompletesPendingServerCallWithoutSendingAnotherFrame(): void
    {
        $clients = new ClientRegistry();
        $client = $this->createMock(WebsocketClient::class);
        $clients->register('CP-01', $client);
        $codec = new FrameCodec();
        $sentId = null;
        $client->method('sendText')->willReturnCallback(function (string $text) use ($codec, &$sentId): void {
            $sentId = $codec->decode($text)->uniqueId;
        });
        $sender = new OutboundCallSender($clients, $codec);
        $pending = $sender->sendCall('CP-01', 'Reset', new \stdClass());
        $transport = new Ocpp16ClientHandler($this->createMock(CallHandler::class), $codec, '/ocpp/', new NullLogger(), null, $clients, $sender);

        self::assertNull($transport->respond('CP-01', $codec->encode(new \Aconnect\OcppBundle\Protocol\V16\CallResult($sentId, (object) ['status' => 'Accepted'])), $client));
        self::assertSame('Accepted', $pending->await()->payload->status);
    }
}
