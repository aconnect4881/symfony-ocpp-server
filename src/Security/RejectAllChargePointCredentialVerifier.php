<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Security;

/** Fail-closed default until the installing application supplies a verifier. */
final class RejectAllChargePointCredentialVerifier implements ChargePointCredentialVerifier
{
    public function verify(string $chargePointIdentity, string $password): bool
    {
        return false;
    }
}
