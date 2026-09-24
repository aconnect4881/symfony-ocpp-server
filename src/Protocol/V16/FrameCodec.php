<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** Encodes and decodes OCPP 1.6 JSON envelopes, without action payload validation. */
final class FrameCodec
{
    /** @throws InvalidFrameException */
    public function decode(string $json): Call|CallResult|CallError
    {
        try {
            $frame = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidFrameException('Invalid JSON frame.', 0, $exception);
        }

        if (!is_array($frame) || !isset($frame[0]) || !is_int($frame[0])) {
            throw new InvalidFrameException('The frame must be a JSON array with a numeric message type.');
        }

        return match ($frame[0]) {
            2 => $this->decodeCall($frame),
            3 => $this->decodeCallResult($frame),
            4 => $this->decodeCallError($frame),
            default => throw new InvalidFrameException('Unknown OCPP message type.'),
        };
    }

    /** @throws InvalidFrameException */
    public function encode(Call|CallResult|CallError $message): string
    {
        self::assertUniqueId($message->uniqueId);

        if ($message instanceof Call) {
            self::assertAction($message->action);
            $frame = [2, $message->uniqueId, $message->action, $message->payload];
        } elseif ($message instanceof CallResult) {
            $frame = [3, $message->uniqueId, $message->payload];
        } else {
            self::assertErrorCode($message->errorCode);
            $frame = [4, $message->uniqueId, $message->errorCode, $message->description, $message->details];
        }

        try {
            return json_encode($frame, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new InvalidFrameException('Could not encode the OCPP frame.', 0, $exception);
        }
    }

    /** @param array<mixed> $frame */
    private function decodeCall(array $frame): Call
    {
        if (count($frame) !== 4 || !isset($frame[2], $frame[3]) || !is_string($frame[2]) || !($frame[3] instanceof \stdClass)) {
            throw new InvalidFrameException('Invalid CALL envelope.');
        }

        self::assertUniqueId($frame[1] ?? null);
        self::assertAction($frame[2]);

        return new Call($frame[1], $frame[2], $frame[3]);
    }

    /** @param array<mixed> $frame */
    private function decodeCallResult(array $frame): CallResult
    {
        if (count($frame) !== 3 || !isset($frame[2]) || !($frame[2] instanceof \stdClass)) {
            throw new InvalidFrameException('Invalid CALLRESULT envelope.');
        }

        self::assertUniqueId($frame[1] ?? null);

        return new CallResult($frame[1], $frame[2]);
    }

    /** @param array<mixed> $frame */
    private function decodeCallError(array $frame): CallError
    {
        if (count($frame) !== 5 || !isset($frame[2], $frame[3], $frame[4]) || !is_string($frame[3]) || !($frame[4] instanceof \stdClass)) {
            throw new InvalidFrameException('Invalid CALLERROR envelope.');
        }

        self::assertUniqueId($frame[1] ?? null);
        self::assertErrorCode($frame[2]);

        return new CallError($frame[1], $frame[2], $frame[3], $frame[4]);
    }

    private static function assertUniqueId(mixed $uniqueId): void
    {
        // OCPP limits the identifier to 36 Unicode characters.
        $length = is_string($uniqueId) ? preg_match_all('/./us', $uniqueId) : false;
        if ($length === false || $length < 1 || $length > 36) {
            throw new InvalidFrameException('The unique ID must contain 1 to 36 characters.');
        }
    }

    private static function assertAction(mixed $action): void
    {
        if (!is_string($action) || $action === '') {
            throw new InvalidFrameException('The CALL action must be a non-empty string.');
        }
    }

    private static function assertErrorCode(mixed $errorCode): void
    {
        if (!is_string($errorCode) || $errorCode === '') {
            throw new InvalidFrameException('The CALLERROR code must be a non-empty string.');
        }
    }
}
