<?php

declare(strict_types=1);

// Example: copy into the host application's src/Ocpp/ directory.
namespace App\Ocpp;

use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Doctrine\DBAL\Connection;

final class DatabaseChargePointCredentialVerifier implements ChargePointCredentialVerifier
{
    private readonly string $unknownPasswordHash;

    public function __construct(private readonly Connection $connection)
    {
        // Do a password check for unknown or disabled identities as well.
        $this->unknownPasswordHash = password_hash(base64_encode(random_bytes(32)), PASSWORD_ARGON2ID);
    }

    public function verify(string $chargePointIdentity, string $password): bool
    {
        $record = $this->connection->fetchAssociative(
            'SELECT identity, password_hash, enabled FROM ocpp_charge_points WHERE identity = ?',
            [$chargePointIdentity],
        );

        // Explicit comparison also preserves case sensitivity if the DB collation does not.
        $active = $record !== false
            && hash_equals((string) $record['identity'], $chargePointIdentity)
            && (int) $record['enabled'] === 1;

        $hash = $active ? (string) $record['password_hash'] : $this->unknownPasswordHash;

        // Base64 handles even binary OCPP passwords (including NUL bytes).
        return password_verify(base64_encode($password), $hash) && $active;
    }
}
