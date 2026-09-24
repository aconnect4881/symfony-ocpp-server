# Symfony OCPP Server

A reusable Symfony bundle for building an OCPP server in a host application.

The package includes a Symfony bundle entry point, an OCPP 1.6 JSON envelope
codec for CALL, CALLRESULT and CALLERROR messages, and an HTTP Basic
authentication gate for the WebSocket handshake. An AMPHP WebSocket acceptor
performs the authenticated OCPP 1.6 upgrade. The `ocpp:server:start` command
starts a WebSocket listener configured in
`config/packages/aconnect_ocpp.yaml`. The bundle owns the WebSocket and OCPP
frame loop. The host application supplies a credential verifier and a handler
for its own OCPP action responses. It supports direct TLS and an explicitly
trusted TLS-terminating reverse proxy. OCPP action payload validation has not
been implemented yet. The server
process also owns a client registry and sender for outgoing CALLs with reply
correlation and timeouts. Messenger polling can be enabled for a dedicated
queue; the host app still supplies its own message handlers.

See [the package documentation](docs/index.md) for configuration, service
bindings, startup, and a codec example.
For the installing application, follow the [short quickstart](docs/quickstart.md).

A [Symfony Flex recipe draft](flex-recipe/aconnect4881/symfony-ocpp-server/0.1/)
adds a commented `config/packages/aconnect_ocpp.yaml`. Flex can apply it
automatically once the recipe is published to `symfony/recipes-contrib`; merely
shipping it inside this package does not register it with Flex. Until then,
copy the recipe YAML to the host application's `config/packages/` directory.

## Development

The package requires PHP 8.2 or newer and supports Symfony HttpKernel 6.4, 7.x,
and 8.x, subject to each Symfony version's own PHP requirements. Install
dependencies with `composer install` in a PHP environment. Run `composer test`
to execute the codec tests.

Licensed under [MIT](LICENSE).
