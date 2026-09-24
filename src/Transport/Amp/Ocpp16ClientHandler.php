<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Amp;

use Aconnect\OcppBundle\Protocol\V16\Call;
use Aconnect\OcppBundle\Protocol\V16\CallError;
use Aconnect\OcppBundle\Protocol\V16\CallHandler;
use Aconnect\OcppBundle\Protocol\V16\CallResult;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Aconnect\OcppBundle\Protocol\V16\InvalidFrameException;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\WebsocketClient;
use Amp\Websocket\WebsocketCloseCode;
use Psr\Log\LoggerInterface;

/** Owns the WebSocket frame loop and OCPP 1.6 JSON envelope processing. */
final readonly class Ocpp16ClientHandler implements WebsocketClientHandler
{
    public function __construct(
        private CallHandler $actions,
        private FrameCodec $codec,
        private string $pathPrefix,
        private LoggerInterface $logger,
        private ?ConnectionObserver $observer = null,
        private ?ClientRegistry $clients = null,
        private ?OutboundCallSender $outbound = null,
    ) {
    }

    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        // The acceptor has already authenticated the one URL segment after this prefix.
        $identity = rawurldecode(substr($request->getUri()->getPath(), strlen($this->pathPrefix)));

        $previous = $this->clients?->register($identity, $client);
        if ($previous !== null && $previous !== $client) {
            $this->outbound?->disconnect($identity, $previous);
            $previous->close();
        }

        $this->notify($identity, true);
        try {
            foreach ($client as $message) {
                if ($this->clients !== null && $this->clients->get($identity) !== $client) {
                    return;
                }
                if ($message->isBinary()) {
                    $client->close(WebsocketCloseCode::UNACCEPTABLE_TYPE, 'OCPP requires text frames');

                    return;
                }

                try {
                    $reply = $this->respond($identity, $message->buffer(), $client);
                    if ($reply !== null) {
                        $client->sendText($reply);
                    }
                } catch (InvalidFrameException $exception) {
                    $client->close(WebsocketCloseCode::PROTOCOL_ERROR, 'Invalid OCPP message');

                    return;
                }
            }
        } finally {
            $current = $this->clients?->unregister($identity, $client) ?? true;
            $this->outbound?->disconnect($identity, $client);
            if ($current) {
                $this->notify($identity, false);
            }
        }
    }

    private function notify(string $identity, bool $connected): void
    {
        if ($this->observer === null) {
            return;
        }

        try {
            if ($connected) {
                $this->observer->connected($identity);
            } else {
                $this->observer->disconnected($identity);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('OCPP connection observer failed', [
                'identity' => $identity,
                'connected' => $connected,
                'exception' => $exception,
            ]);
        }
    }

    /** @throws InvalidFrameException */
    public function respond(string $identity, string $json, ?WebsocketClient $client = null): ?string
    {
        $call = $this->codec->decode($json);
        if (!$call instanceof Call) {
            if ($client === null || !$this->outbound?->resolve($identity, $client, $call)) {
                throw new InvalidFrameException('Unexpected OCPP response without a matching request.');
            }

            return null;
        }

        try {
            $result = $this->actions->handle($identity, $call);
        } catch (\Throwable $exception) {
            $this->logger->error('OCPP action failed', [
                'identity' => $identity,
                'action' => $call->action,
                'exception' => $exception,
            ]);
            $result = new CallError($call->uniqueId, 'InternalError', 'Action failed', new \stdClass());
        }

        if ($result instanceof CallError) {
            $result = new CallError($call->uniqueId, $result->errorCode, $result->description, $result->details);
        } else {
            $result = new CallResult($call->uniqueId, $result);
        }

        return $this->codec->encode($result);
    }
}
