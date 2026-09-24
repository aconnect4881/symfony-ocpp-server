<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Amp;

use Aconnect\OcppBundle\Protocol\V16\Call;
use Aconnect\OcppBundle\Protocol\V16\CallError;
use Aconnect\OcppBundle\Protocol\V16\CallResult;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Websocket\WebsocketClient;
use Revolt\EventLoop;

/** Sends central-system CALLs and matches replies to their connection and ID. */
final class OutboundCallSender
{
    /** @var array<string, array<string, array{client: WebsocketClient, deferred: DeferredFuture, timer: string}>> */
    private array $pending = [];

    public function __construct(private ClientRegistry $clients, private FrameCodec $codec)
    {
    }

    /** @return Future<CallResult|CallError> */
    public function sendCall(string $identity, string $action, \stdClass $payload, float $timeoutSeconds = 30.0): Future
    {
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('The OCPP reply timeout must be positive.');
        }

        $client = $this->clients->get($identity);
        if ($client === null) {
            throw new \RuntimeException(sprintf('Charge point "%s" is not connected to this server process.', $identity));
        }

        $id = bin2hex(random_bytes(16));
        $frame = $this->codec->encode(new Call($id, $action, $payload));
        $deferred = new DeferredFuture();
        $timer = EventLoop::delay($timeoutSeconds, function () use ($identity, $id, $client): void {
            $this->fail($identity, $id, $client, new \RuntimeException('Timed out waiting for an OCPP response.'));
        });
        $this->pending[$identity][$id] = ['client' => $client, 'deferred' => $deferred, 'timer' => $timer];

        try {
            $client->sendText($frame);
        } catch (\Throwable $exception) {
            $this->fail($identity, $id, $client, $exception);
        }

        return $deferred->getFuture();
    }

    public function resolve(string $identity, WebsocketClient $client, CallResult|CallError $reply): bool
    {
        $pending = $this->pending[$identity][$reply->uniqueId] ?? null;
        if ($pending === null || $pending['client'] !== $client) {
            return false;
        }

        $this->forget($identity, $reply->uniqueId, $pending['timer']);
        $pending['deferred']->complete($reply);

        return true;
    }

    public function disconnect(string $identity, WebsocketClient $client): void
    {
        foreach ($this->pending[$identity] ?? [] as $id => $pending) {
            if ($pending['client'] === $client) {
                $this->fail($identity, $id, $client, new \RuntimeException('The charge point disconnected before responding.'));
            }
        }
    }

    private function fail(string $identity, string $id, WebsocketClient $client, \Throwable $reason): void
    {
        $pending = $this->pending[$identity][$id] ?? null;
        if ($pending === null || $pending['client'] !== $client) {
            return;
        }

        $this->forget($identity, $id, $pending['timer']);
        $pending['deferred']->error($reason);
    }

    private function forget(string $identity, string $id, string $timer): void
    {
        EventLoop::cancel($timer);
        unset($this->pending[$identity][$id]);
        if (($this->pending[$identity] ?? []) === []) {
            unset($this->pending[$identity]);
        }
    }
}
