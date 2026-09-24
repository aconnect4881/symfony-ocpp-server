<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** OCPP 1.6 JSON request: [2, uniqueId, action, payload]. */
final readonly class Call
{
    public function __construct(
        public string $uniqueId,
        public string $action,
        public \stdClass $payload,
    ) {
    }
}
