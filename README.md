# Symfony OCPP Server

A reusable Symfony bundle for building an OCPP server in a host application.

The package includes a Symfony bundle entry point, an OCPP 1.6 JSON envelope
codec for CALL, CALLRESULT and CALLERROR messages, and an HTTP Basic
authentication gate for the WebSocket handshake. An AMPHP WebSocket acceptor
performs the authenticated OCPP 1.6 upgrade. The `ocpp:server:start` command
starts a direct TLS WebSocket listener configured in
`config/packages/aconnect_ocpp.yaml`. The host application provides a credential
verifier and a connection handler. Action payload validation and host
application message handling have not been implemented yet.

See [the package documentation](docs/index.md) for configuration, service
bindings, startup, and a codec example.

## Development

The package requires PHP 8.2 or newer and supports Symfony HttpKernel 6.4, 7.x,
and 8.x, subject to each Symfony version's own PHP requirements. Install
dependencies with `composer install` in a PHP environment. Run `composer test`
to execute the codec tests.

Licensed under [MIT](LICENSE).
