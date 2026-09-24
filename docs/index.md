# Symfony OCPP Server bundle

This package is a reusable Symfony bundle with an OCPP 1.6 JSON envelope codec
and an HTTP Basic authentication gate for AMPHP WebSocket Server. It does not
yet provide OCPP action payload validation or a host application's message
handlers and credential store.

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

## Start a direct WSS listener

Copy [`examples/aconnect_ocpp.yaml`](../examples/aconnect_ocpp.yaml) to the
host application's `config/packages/aconnect_ocpp.yaml`:

```yaml
aconnect_ocpp:
    host: '0.0.0.0'
    port: 9000
    path_prefix: '/ocpp/'
    credential_verifier_service: 'App\Ocpp\DatabaseChargePointCredentialVerifier'
    client_handler_service: 'App\Ocpp\ChargePointConnectionHandler'
    tls:
        certificate: '%env(resolve:OCPP_TLS_CERTIFICATE)%'
        private_key: '%env(resolve:OCPP_TLS_PRIVATE_KEY)%'
```

Set `OCPP_TLS_CERTIFICATE` and `OCPP_TLS_PRIVATE_KEY` to readable absolute paths
to the certificate chain and its unencrypted private key. Keep the private key
outside the web root and do not commit it. Make sure the certificate covers the
hostname used by the charging stations. Bind `host` to an address accessible to
the stations; `0.0.0.0` binds all IPv4 interfaces. The default host is
`127.0.0.1`, port `9000`, and path prefix `/ocpp/`. TLS is mandatory; an
invalid or mismatched key fails at startup. The server only accepts direct TLS
connections, not proxy-terminated TLS.

The `credential_verifier_service` and `client_handler_service` settings name
Symfony services. With the standard `App\:` service registration in
`config/services.yaml`, the example class names are discovered automatically:
no extra aliases are needed. The verifier class must implement
`ChargePointCredentialVerifier`, and the handler must implement AMPHP's
`WebsocketClientHandler`. The command receives both services through Symfony's
container. If you omit these two config settings, the package uses the two
interface names as service IDs, which can instead be aliased in
`config/services.yaml`.

For a database-backed first version, see
[`DatabaseChargePointCredentialVerifier.php`](../examples/host-app/DatabaseChargePointCredentialVerifier.php)
and the example [SQL table](../examples/host-app/ocpp_charge_points.sql). Copy
the class to `src/Ocpp/` in the host app and create the table with that app's
normal migration tool. This example uses Doctrine DBAL: install DoctrineBundle
and DBAL in the host app if necessary and configure its database connection.
The package itself does not require Doctrine or impose a schema. Provision a
unique random secret for each charger, for example `bin2hex(random_bytes(32))`.
Store only `password_hash(base64_encode($secret), PASSWORD_ARGON2ID)` in the
table; share the original secret with the charger at provisioning. Base64
preserves binary OCPP passwords, and the verifier checks it using
`password_verify(base64_encode($password), $storedHash)`. The host app can
disable a charger with `enabled = 0` and rotate its secret by replacing the
hash. This simple DBAL implementation performs a synchronous query and password
check on the server loop; evaluate a nonblocking credential store or workers
before serving many concurrent chargers.

The handler receives an authenticated WebSocket client and can decode OCPP 1.6
text frames with `FrameCodec`. The package does not decide what OCPP actions
mean to your app; `App\Ocpp\ChargePointConnectionHandler` is a placeholder
until the host app implements that behavior.

Start the process in the host application:

```bash
php bin/console ocpp:server:start
```

The charging station connects to
`wss://your-hostname.example:9000/ocpp/<url-encoded-charge-point-id>` using
HTTP Basic and the `ocpp1.6` subprotocol. Run the command as a long-lived
service; SIGINT and SIGTERM stop the server. Ensure the host firewall allows the
configured port.

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

## Charge point authentication

OCPP 1.6 Security Profile 2 uses HTTP Basic over TLS. Authenticate the HTTP
request before upgrading it to WebSocket. The Basic username must exactly match
the charge point identity from the connection URL. The command extracts that
identity from a URL and terminates TLS. The package does not store passwords
or issue tokens.

Implement `ChargePointCredentialVerifier` in the host application. Its `verify`
method receives the URL identity and the decoded raw password bytes; a charger
may use a binary password containing NUL or colon bytes. Return `false` for an
unknown or disabled identity, or an incorrect password. Provision a separate
random secret for each charger, store verification material securely, and make
the comparison resistant to timing attacks. The gate rejects passwords shorter
than 16 bytes and malformed Basic headers.

```php
use Aconnect\OcppBundle\Security\BasicAuthenticator;

$authenticator = new BasicAuthenticator($hostCredentialVerifier);
$authorized = $authenticator->authenticate(
    $chargePointIdentityFromUrl,
    $authorizationHeader,
    $trustedTlsStatus,
);

if (!$authorized) {
    // Return HTTP 401 and WWW-Authenticate: Basic realm="OCPP".
    // Do not send HTTP 101 Switching Protocols.
}
```

Set `$trustedTlsStatus` from the actual TLS connection or a trusted TLS
terminator. Never infer it from an untrusted `X-Forwarded-Proto` header. Reject
multiple Authorization header fields at the transport boundary. Do not log the
header or password. The host must rate-limit failed upgrades, monitor failed
authentications, rotate credentials, and decide how to handle simultaneous
connections from the same charge point.

## AMPHP WebSocket transport

The package uses `amphp/websocket-server` 4.x on `amphp/http-server` 3.x.
`Ocpp16Acceptor` accepts only a **direct TLS connection** to the AMPHP server.
It reads one URL segment after `/ocpp/` as the charge point identity, checks
the `ocpp1.6` subprotocol and a single Basic Authorization header, then
delegates the RFC 6455 upgrade to AMPHP. Invalid credentials receive HTTP 401
before the upgrade. Encoded slashes, an absent subprotocol, or a cleartext
connection are rejected.

The `ocpp:server:start` command mounts the acceptor on a TLS-enabled AMPHP
`Websocket` endpoint. To embed the acceptor in a different server process, use:

```php
use Aconnect\OcppBundle\Security\BasicAuthenticator;
use Aconnect\OcppBundle\Transport\Amp\Ocpp16Acceptor;
use Amp\Websocket\Server\Websocket;

$acceptor = new Ocpp16Acceptor(new BasicAuthenticator($hostCredentialVerifier));
$endpoint = new Websocket($tlsHttpServer, $logger, $acceptor, $hostClientHandler);
$tlsHttpServer->start($endpoint, $errorHandler);
```

The command configures the AMPHP listener with a TLS certificate and exposes
`wss://` to charge points. A plain HTTP listener, even if its request URI says
`https`, is rejected because TLS is checked on the actual socket. If TLS is
terminated at a reverse proxy, this acceptor will reject the internal
cleartext hop; a trusted proxy integration must be designed for that topology.
Your client handler can decode text messages with `FrameCodec`; it must reject
binary frames and validate OCPP action payloads before handling them. Do not
log Authorization headers. Avoid blocking credential-store I/O on AMPHP's event
loop and rate-limit failed handshakes at the server or edge.

## Next design decisions

Integrate the host credential verifier and message handler. Add OCPP action
schemas and request correlation before processing operational messages.
