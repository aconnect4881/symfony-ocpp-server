<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Tests\Transport\Amp;

use Aconnect\OcppBundle\Security\BasicAuthenticator;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Aconnect\OcppBundle\Transport\Amp\Ocpp16Acceptor;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Socket\TlsInfo;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;

/** Verifies that only an authorized TLS request can reach HTTP 101. */
final class Ocpp16AcceptorTest extends TestCase
{
    public function testAuthorizedHandshakeNegotiatesOcppSubprotocol(): void
    {
        $verifier = $this->verifier();
        $request = $this->request(
            '/ocpp/CP-01',
            ['authorization' => 'Basic '.base64_encode('CP-01:'.str_repeat('x', 16))],
        );

        $response = (new Ocpp16Acceptor(new BasicAuthenticator($verifier)))->handleHandshake($request);

        self::assertSame(HttpStatus::SWITCHING_PROTOCOLS, $response->getStatus());
        self::assertSame('ocpp1.6', $response->getHeader('Sec-WebSocket-Protocol'));
        self::assertSame(1, $verifier->calls);
    }

    public function testRejectsMissingOrDuplicatedBasicCredentialsBeforeUpgrade(): void
    {
        $verifier = $this->verifier();
        $acceptor = new Ocpp16Acceptor(new BasicAuthenticator($verifier));

        foreach ([[], ['authorization' => ['Basic abc', 'Basic def']]] as $headers) {
            $response = $acceptor->handleHandshake($this->request('/ocpp/CP-01', $headers));
            self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
            self::assertSame('Basic realm="OCPP"', $response->getHeader('WWW-Authenticate'));
        }

        self::assertSame(0, $verifier->calls);
    }

    public function testRejectsCleartextConnectionEvenWithValidCredentials(): void
    {
        $verifier = $this->verifier();
        $request = $this->request(
            '/ocpp/CP-01',
            ['authorization' => 'Basic '.base64_encode('CP-01:'.str_repeat('x', 16))],
            false,
        );

        $response = (new Ocpp16Acceptor(new BasicAuthenticator($verifier)))->handleHandshake($request);

        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        self::assertSame(0, $verifier->calls);
    }

    public function testRejectsEncodedSlashAndMissingOcppSubprotocol(): void
    {
        $verifier = $this->verifier();
        $acceptor = new Ocpp16Acceptor(new BasicAuthenticator($verifier));

        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $acceptor->handleHandshake($this->request('/ocpp/CP%2F01'))->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $acceptor->handleHandshake($this->request('/ocpp/CP-01', ['sec-websocket-protocol' => 'other']))->getStatus(),
        );
        self::assertSame(0, $verifier->calls);
    }

    /** @param array<string, string|array<string>> $headers */
    private function request(string $path, array $headers = [], bool $tls = true): Request
    {
        $client = $this->createMock(Client::class);
        $client->method('getTlsInfo')->willReturn($tls ? TlsInfo::fromMetaData([
            'protocol' => 'TLSv1.3',
            'cipher_name' => 'TLS_AES_128_GCM_SHA256',
            'cipher_bits' => 128,
            'cipher_version' => 'TLSv1.3',
        ], []) : null);

        return new Request($client, 'GET', Http::new('https://example.test'.$path), array_replace([
            'upgrade' => 'websocket',
            'connection' => 'Upgrade',
            'sec-websocket-key' => base64_encode(str_repeat('a', 16)),
            'sec-websocket-version' => '13',
            'sec-websocket-protocol' => 'ocpp1.6',
        ], $headers));
    }

    private function verifier(): ChargePointCredentialVerifier
    {
        return new class implements ChargePointCredentialVerifier {
            public int $calls = 0;

            public function verify(string $chargePointIdentity, string $password): bool
            {
                ++$this->calls;

                return $chargePointIdentity === 'CP-01' && hash_equals(str_repeat('x', 16), $password);
            }
        };
    }
}
