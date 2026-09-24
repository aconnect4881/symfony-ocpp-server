<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Security;

/**
 * Checks OCPP 1.6 HTTP Basic credentials before a WebSocket upgrade.
 *
 * The caller must supply the identity from the connection URL and a trusted
 * TLS status from its server or trusted TLS terminator.
 */
final readonly class BasicAuthenticator
{
    public function __construct(private ChargePointCredentialVerifier $verifier)
    {
    }

    public function authenticate(
        string $chargePointIdentity,
        ?string $authorizationHeader,
        bool $isSecureTransport,
    ): bool {
        if (!$isSecureTransport || $chargePointIdentity === '' || preg_match('/[\x00-\x1F\x7F:]/', $chargePointIdentity)) {
            return false;
        }

        if ($authorizationHeader === null || strlen($authorizationHeader) > 4096) {
            return false;
        }

        // Reject extra header values, whitespace inside the token, and other schemes.
        if (!preg_match('~^Basic[ \t]+([A-Za-z0-9+/]+={0,2})$~iD', $authorizationHeader, $matches)) {
            return false;
        }

        $credentials = base64_decode($matches[1], true);
        if ($credentials === false) {
            return false;
        }

        $separator = strpos($credentials, ':');
        if ($separator === false) {
            return false;
        }

        $username = substr($credentials, 0, $separator);
        $password = substr($credentials, $separator + 1);

        if (!hash_equals($chargePointIdentity, $username) || strlen($password) < 16) {
            return false;
        }

        return $this->verifier->verify($chargePointIdentity, $password);
    }
}
