# Symfony OCPP Server bundle

This package is a reusable Symfony bundle. Its current implementation provides
the bundle entry point and Composer metadata only. It does not yet provide a
WebSocket server, OCPP messages, charge point authentication, or token handling.

## Installation

Once the package is available to Composer, install it in a Symfony application:

```bash
composer require aconnect4881/symfony-ocpp-server
```

If Symfony Flex does not register the bundle automatically, add it to the host
application's `config/bundles.php`:

```php
return [
    Aconnect\OcppBundle\AconnectOcppBundle::class => ['all' => true],
];
```

## Next design decisions

Before implementing the server, choose the OCPP protocol version, the WebSocket
transport, and how the host application identifies and authorizes charge points.
The bundle should accept host-provided credentials or authenticators rather than
ship shared secrets or issue tokens without a defined policy.
