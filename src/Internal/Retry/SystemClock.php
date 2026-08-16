<?php

declare(strict_types=1);

namespace ViewMend\Internal\Retry;

use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
