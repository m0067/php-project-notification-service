<?php

declare(strict_types=1);

namespace App\Gateways;

enum GatewayOutcome
{
    case Accepted;
    case Transient;
    case Permanent;
}
