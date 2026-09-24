# Symfony OCPP Server

A reusable Symfony bundle for building an OCPP server in a host application.

The package includes a Symfony bundle entry point and an OCPP 1.6 JSON envelope
codec for CALL, CALLRESULT and CALLERROR messages. Action payload validation,
WebSocket transport, charge point authentication and token management have not
been implemented yet.

See [the package documentation](docs/index.md) for installation, a codec example,
and the next integration decisions.

## Development

The package requires PHP 8.2 or newer and supports Symfony HttpKernel 6.4, 7.x,
and 8.x, subject to each Symfony version's own PHP requirements. Install
dependencies with `composer install` in a PHP environment. Run `composer test`
to execute the codec tests.

Licensed under [MIT](LICENSE).
