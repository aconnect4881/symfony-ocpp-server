# Quickstart for a Symfony application

The bundle runs the OCPP WebSocket server; the application supplies charger
credentials and business responses to incoming OCPP actions. You do not need an
application-specific WebSocket command, acceptor, client registry or sender.

## 1. Install and configure the application services

Once the Composer package is published, install it with:

```bash
composer config extra.symfony.allow-contrib true
composer require aconnect4881/symfony-ocpp-server
```

To preview the **current draft branch** in your existing app before a release,
add its GitHub repository to that app's Composer configuration instead:

```bash
composer config repositories.aconnect_ocpp vcs https://github.com/aconnect4881/symfony-ocpp-server
composer require 'aconnect4881/symfony-ocpp-server:dev-codex/package-scaffold-20260924-42njbf'
```

Symfony Flex enables the bundle. Its commented configuration file will only be
created automatically **after** the draft recipe has been accepted into
`symfony/recipes-contrib`. Until then, copy
[`aconnect_ocpp.yaml`](../flex-recipe/aconnect4881/symfony-ocpp-server/0.1/config/packages/aconnect_ocpp.yaml)
to `config/packages/aconnect_ocpp.yaml` in the application. The current draft
PR is not a released Composer package.

Provide two application services and set their IDs in that YAML:

```yaml
aconnect_ocpp:
    credential_verifier_service: 'App\Ocpp\ChargePointVerifier'
    action_handler_service: 'App\Ocpp\ChargePointActionHandler'
```

`ChargePointVerifier` must implement
[`ChargePointCredentialVerifier`](../src/Security/ChargePointCredentialVerifier.php).
It checks the identity and raw Basic password against your own charger records.
The [DBAL verifier example](../examples/host-app/DatabaseChargePointCredentialVerifier.php)
shows one possible implementation. `ChargePointActionHandler` must implement
[`CallHandler`](../src/Protocol/V16/CallHandler.php); it returns a response
payload (`stdClass`) or a `CallError` for each incoming charger action. Your
existing per-action handlers can be called from this one adapter. With a
standard Symfony `App\:` service configuration, classes in `src/Ocpp/` are
registered automatically.

Without your verifier, the bundle deliberately rejects every charger. The
default action handler also responds `NotSupported` to every action.

## 2. Choose where TLS terminates

For charging stations that connect **directly** to the command, set
`transport: direct_tls`, bind the listener to the appropriate interface, and
provide readable certificate and private key paths under `tls`:

```yaml
aconnect_ocpp:
    host: '0.0.0.0'
    transport: direct_tls
    tls:
        certificate: '%env(resolve:OCPP_TLS_CERTIFICATE)%'
        private_key: '%env(resolve:OCPP_TLS_PRIVATE_KEY)%'
```

For a **reverse proxy** that terminates public WSS, set
`transport: trusted_proxy`, use the actual proxy IP or CIDR, and keep the
backend listener inaccessible to charging stations. The proxy must overwrite
`X-Forwarded-Proto` with `https` for HTTPS requests:

```yaml
aconnect_ocpp:
    host: '127.0.0.1'
    transport: trusted_proxy
    trusted_proxies: ['127.0.0.1'] # Replace with the proxy's actual peer IP.
```

These snippets show only the TLS-related keys: put them **under the same**
`aconnect_ocpp:` key as the service IDs from step 1. The command uses port
`8081` unless `PORT` is set by the runtime; direct WSS needs a public route to
that listener, while a proxy exposes its own public `wss://` URL.

Start the server as a long-lived process:

```bash
php bin/console ocpp:server:start
```

The charger connects to `wss://your-host/ocpp/<url-encoded-identity>` using
HTTP Basic (username equal to the URL identity) and the `ocpp1.6` subprotocol.

## 3. Optional: queue server-initiated commands with Messenger

If the application sends commands to chargers, install `symfony/messenger`
and configure a dedicated transport in `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            ocpp: '%env(OCPP_MESSENGER_DSN)%'
        routing:
            'App\Message\SendReset': ocpp
```

Add `messenger_transport: ocpp` beneath the same `aconnect_ocpp:` key. A host
handler for `App\Message\SendReset`, marked
`#[AsMessageHandler(fromTransport: 'ocpp')]`, can
inject `OutboundCallSender` and call:

```php
$reply = $sender->sendCall(
    $message->chargePointIdentity,
    'Reset',
    (object) ['type' => 'Soft'],
)->await();
```

The bundle runs that handler inside the WebSocket process, so it can reach
connected chargers. `await()` returns `CallResult` or `CallError`; timeout and
disconnect errors throw. Set `OCPP_MESSENGER_DSN` to your dedicated queue's DSN.
The application decides how to record the outcome.
Use a queue receiver whose `get()` returns promptly, and do not run another
worker against this dedicated `ocpp` transport. Messenger is optional: omit
this entire step if the app only responds to charger-initiated messages.
