<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Protocol\V16;

use Aconnect\OcppBundle\Protocol\V16\Call;
use Aconnect\OcppBundle\Protocol\V16\CallError;
use Aconnect\OcppBundle\Protocol\V16\CallResult;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Aconnect\OcppBundle\Protocol\V16\InvalidFrameException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercises wire envelopes, including distinct JSON objects and arrays. */
final class FrameCodecTest extends TestCase
{
    public function testCallRoundTripPreservesNestedObjectsAndArrays(): void
    {
        $codec = new FrameCodec();
        $json = '[2,"request-1","BootNotification",{"chargePointVendor":"ACME","values":[1,2],"nested":{}}]';

        $message = $codec->decode($json);

        self::assertInstanceOf(Call::class, $message);
        self::assertSame('BootNotification', $message->action);
        self::assertSame('ACME', $message->payload->chargePointVendor);
        self::assertSame([1, 2], $message->payload->values);
        self::assertInstanceOf(\stdClass::class, $message->payload->nested);
        self::assertSame($json, $codec->encode($message));
    }

    public function testEmptyPayloadRemainsAnObject(): void
    {
        $codec = new FrameCodec();
        $message = $codec->decode('[3,"request-1",{}]');

        self::assertInstanceOf(CallResult::class, $message);
        self::assertSame('[3,"request-1",{}]', $codec->encode($message));
    }

    public function testErrorRoundTrip(): void
    {
        $codec = new FrameCodec();
        $json = '[4,"request-1","NotSupported","Unknown action",{}]';

        $message = $codec->decode($json);

        self::assertInstanceOf(CallError::class, $message);
        self::assertSame('NotSupported', $message->errorCode);
        self::assertSame($json, $codec->encode($message));
    }

    #[DataProvider('invalidFrames')]
    public function testRejectsMalformedEnvelopes(string $json): void
    {
        $this->expectException(InvalidFrameException::class);

        (new FrameCodec())->decode($json);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFrames(): iterable
    {
        yield 'invalid JSON' => ['[2,'];
        yield 'object root' => ['{}'];
        yield 'string message type' => ['["2","id","Heartbeat",{}]'];
        yield 'unknown message type' => ['[5,"id",{}]'];
        yield 'missing payload' => ['[2,"id","Heartbeat"]'];
        yield 'array payload' => ['[2,"id","Heartbeat",[]]'];
        yield 'null payload' => ['[3,"id",null]'];
        yield 'extra member' => ['[3,"id",{},0]'];
        yield 'empty ID' => ['[3,"",{}]'];
        yield 'long ID' => ['[3,"'.str_repeat('x', 37).'",{}]'];
        yield 'empty action' => ['[2,"id","",{}]'];
        yield 'empty error code' => ['[4,"id","","error",{}]'];
        yield 'non-string error description' => ['[4,"id","GenericError",42,{}]'];
        yield 'array error details' => ['[4,"id","GenericError","error",[]]'];
    }

    public function testRejectsInvalidOutgoingIdentifier(): void
    {
        $this->expectException(InvalidFrameException::class);

        (new FrameCodec())->encode(new Call('', 'Heartbeat', new \stdClass()));
    }
}
