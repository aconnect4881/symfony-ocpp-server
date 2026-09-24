<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** Fail-closed default until the installing application supplies action logic. */
final class NotSupportedCallHandler implements CallHandler
{
    public function handle(string $chargePointIdentity, Call $call): CallError
    {
        return new CallError($call->uniqueId, 'NotSupported', 'Action is not supported.', new \stdClass());
    }
}
