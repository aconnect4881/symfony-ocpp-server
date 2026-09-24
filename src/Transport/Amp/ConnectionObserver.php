<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Transport\Amp;

/** Optional host callback for charger-specific state changes. */
interface ConnectionObserver
{
    public function connected(string $chargePointIdentity): void;

    public function disconnected(string $chargePointIdentity): void;
}
