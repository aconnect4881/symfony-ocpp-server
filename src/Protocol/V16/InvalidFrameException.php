<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle\Protocol\V16;

/** A JSON text frame is malformed or does not have an OCPP 1.6 envelope. */
final class InvalidFrameException extends \InvalidArgumentException
{
}
