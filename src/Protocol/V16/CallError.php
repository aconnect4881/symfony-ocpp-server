<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** OCPP 1.6 JSON error: [4, uniqueId, errorCode, description, details]. */
final readonly class CallError
{
    public function __construct(
        public string $uniqueId,
        public string $errorCode,
        public string $description,
        public \stdClass $details,
    ) {
    }
}
