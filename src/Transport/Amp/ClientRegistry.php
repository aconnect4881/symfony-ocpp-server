<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Amp;

use Amp\Websocket\WebsocketClient;

/** Holds the most recent live connection for each charger in this server process. */
final class ClientRegistry
{
    /** @var array<string, WebsocketClient> */
    private array $clients = [];

    public function register(string $identity, WebsocketClient $client): ?WebsocketClient
    {
        $previous = $this->clients[$identity] ?? null;
        $this->clients[$identity] = $client;

        return $previous;
    }

    public function unregister(string $identity, WebsocketClient $client): bool
    {
        if (($this->clients[$identity] ?? null) !== $client) {
            return false;
        }

        unset($this->clients[$identity]);

        return true;
    }

    public function get(string $identity): ?WebsocketClient
    {
        return $this->clients[$identity] ?? null;
    }
}
