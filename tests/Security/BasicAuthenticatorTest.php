<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Security;

use Aconnect\OcppBundle\Security\BasicAuthenticator;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use PHPUnit\Framework\TestCase;

/** Tests the authentication boundary before a WebSocket upgrade. */
final class BasicAuthenticatorTest extends TestCase
{
    public function testAcceptsBinaryPasswordAndMatchesUrlIdentity(): void
    {
        $password = "\0abc:def:ghijklmnop";
        $verifier = new RecordingVerifier('CP-01', $password);
        $authenticator = new BasicAuthenticator($verifier);
        $header = 'bAsIc '.base64_encode('CP-01:'.$password);

        self::assertTrue($authenticator->authenticate('CP-01', $header, true));
        self::assertSame(1, $verifier->calls);
    }

    public function testRejectsInsecureTransportWithoutConsultingVerifier(): void
    {
        $verifier = new RecordingVerifier('CP-01', str_repeat('x', 16));
        $header = 'Basic '.base64_encode('CP-01:'.str_repeat('x', 16));

        self::assertFalse((new BasicAuthenticator($verifier))->authenticate('CP-01', $header, false));
        self::assertSame(0, $verifier->calls);
    }

    public function testRejectsUrlIdentityMismatchBeforeCredentialLookup(): void
    {
        $verifier = new RecordingVerifier('CP-01', str_repeat('x', 16));
        $header = 'Basic '.base64_encode('CP-02:'.str_repeat('x', 16));

        self::assertFalse((new BasicAuthenticator($verifier))->authenticate('CP-01', $header, true));
        self::assertSame(0, $verifier->calls);
    }

    public function testRejectsMalformedAndShortCredentials(): void
    {
        $verifier = new RecordingVerifier('CP-01', str_repeat('x', 16));
        $authenticator = new BasicAuthenticator($verifier);

        foreach ([
            null,
            'Bearer token',
            'Basic !invalid!',
            'Basic '.base64_encode('CP-01'),
            'Basic '.base64_encode('CP-01:short'),
            'Basic '.base64_encode('CP-01:'.str_repeat('x', 16)).', Basic duplicate',
        ] as $header) {
            self::assertFalse($authenticator->authenticate('CP-01', $header, true));
        }

        self::assertSame(0, $verifier->calls);
    }

    public function testUnknownOrDisabledChargePointIsRejectedByHostVerifier(): void
    {
        $verifier = new RecordingVerifier('CP-01', str_repeat('x', 16));
        $header = 'Basic '.base64_encode('CP-02:'.str_repeat('x', 16));

        self::assertFalse((new BasicAuthenticator($verifier))->authenticate('CP-02', $header, true));
        self::assertSame(1, $verifier->calls);
    }
}

/** Stand-in for the host application's provisioned credential store. */
final class RecordingVerifier implements ChargePointCredentialVerifier
{
    public int $calls = 0;

    public function __construct(private string $identity, private string $password)
    {
    }

    public function verify(string $chargePointIdentity, string $password): bool
    {
        ++$this->calls;

        return hash_equals($this->identity, $chargePointIdentity)
            && hash_equals($this->password, $password);
    }
}
