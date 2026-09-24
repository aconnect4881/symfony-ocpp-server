<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** Host application decides what an OCPP 1.6 CALL means for its own data. */
interface CallHandler
{
    public function handle(string $chargePointIdentity, Call $call): \stdClass|CallError;
}
