<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Security;

/**
 * Host-provided lookup and verification for one provisioned charge point.
 *
 * The password is the raw byte sequence from HTTP Basic; it may contain NUL
 * or colon bytes. Return false for unknown, disabled, or invalid identities.
 */
interface ChargePointCredentialVerifier
{
    public function verify(string $chargePointIdentity, string $password): bool;
}
