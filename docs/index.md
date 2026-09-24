# Symfony OCPP Server bundle

This package is a reusable Symfony bundle with an OCPP 1.6 JSON envelope codec.
It does not yet provide a WebSocket server, OCPP action payload validation,
charge point authentication, or token handling.

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

## OCPP 1.6 JSON messages

`FrameCodec` converts one JSON text message to a `Call`, `CallResult`, or
`CallError`, and back. It verifies the message type, envelope length, field
types, and the 1-to-36-character unique ID. Payloads and error details are
`stdClass` objects so that an empty JSON object remains `{}` on the wire.

```php
use Aconnect\OcppBundle\Protocol\V16\Call;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;

$codec = new FrameCodec();
$message = $codec->decode('[2,"request-1","Heartbeat",{}]');

if ($message instanceof Call) {
    // The host application dispatches $message->action and $message->payload.
    $action = $message->action;
}
```

The codec does not check action names against the OCPP schema, validate their
payloads, correlate a response with an outstanding request, or enforce unique
IDs across a WebSocket connection. The host transport must enforce those rules
where appropriate and negotiate the `ocpp1.6` WebSocket subprotocol.

## Next design decisions

Before implementing the server, choose the WebSocket transport and how the host
application identifies and authorizes charge points.
The bundle should accept host-provided credentials or authenticators rather than
ship shared secrets or issue tokens without a defined policy.
