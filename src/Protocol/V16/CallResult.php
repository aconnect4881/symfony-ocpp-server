<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** OCPP 1.6 JSON response: [3, uniqueId, payload]. */
final readonly class CallResult
{
    public function __construct(
        public string $uniqueId,
        public \stdClass $payload,
    ) {
    }
}
