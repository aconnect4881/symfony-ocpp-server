# Symfony OCPP Server

A reusable Symfony bundle for building an OCPP server in a host application.

The repository currently contains the package skeleton: Composer metadata and a
Symfony bundle entry point. OCPP message handling, transport, authentication,
and token management have not been implemented yet.

See [the package documentation](docs/index.md) for installation and the design
decisions needed before implementing protocol behavior.

## Development

The package requires PHP 8.2 or newer and supports Symfony HttpKernel 6.4, 7.x,
and 8.x, subject to each Symfony version's own PHP requirements. Install
dependencies with `composer install` in a PHP environment.

Licensed under [MIT](LICENSE).
