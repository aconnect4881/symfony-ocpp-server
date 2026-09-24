<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Amp;

use Aconnect\OcppBundle\Security\BasicAuthenticator;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\Rfc6455Acceptor;
use Amp\Websocket\Server\WebsocketAcceptor;

/**
 * Authenticates an OCPP 1.6 WebSocket request before the HTTP 101 upgrade.
 *
 * Only directly TLS-protected connections are accepted. A proxy-terminated
 * connection needs a separately designed, trusted TLS boundary.
 */
final readonly class Ocpp16Acceptor implements WebsocketAcceptor
{
    private WebsocketAcceptor $websocketAcceptor;

    public function __construct(
        private BasicAuthenticator $authenticator,
        private string $pathPrefix = '/ocpp/',
        ?WebsocketAcceptor $websocketAcceptor = null,
    ) {
        if ($pathPrefix === '/' || !str_starts_with($pathPrefix, '/') || !str_ends_with($pathPrefix, '/')) {
            throw new \InvalidArgumentException('The OCPP path prefix must start and end with a slash.');
        }

        $this->websocketAcceptor = $websocketAcceptor ?? new Rfc6455Acceptor();
    }

    public function handleHandshake(Request $request): Response
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, $this->pathPrefix)) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $encodedIdentity = substr($path, strlen($this->pathPrefix));
        if ($encodedIdentity === '' || str_contains($encodedIdentity, '/') || preg_match('/%(?![0-9A-Fa-f]{2})/', $encodedIdentity)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $identity = rawurldecode($encodedIdentity);
        if (str_contains($identity, '/')) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        if ($request->getClient()->getTlsInfo() === null) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        $subprotocols = [];
        foreach ($request->getHeaderArray('sec-websocket-protocol') as $header) {
            array_push($subprotocols, ...array_map('trim', explode(',', $header)));
        }

        if (!in_array('ocpp1.6', $subprotocols, true)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $authorizationHeaders = $request->getHeaderArray('authorization');
        if (count($authorizationHeaders) !== 1 || !$this->authenticator->authenticate($identity, $authorizationHeaders[0], true)) {
            return new Response(HttpStatus::UNAUTHORIZED, ['WWW-Authenticate' => 'Basic realm="OCPP"']);
        }

        $response = $this->websocketAcceptor->handleHandshake($request);
        if ($response->getStatus() === HttpStatus::SWITCHING_PROTOCOLS) {
            $response->setHeader('Sec-WebSocket-Protocol', 'ocpp1.6');
        }

        return $response;
    }
}
