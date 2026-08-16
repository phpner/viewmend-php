<?php

declare(strict_types=1);

namespace ViewMend\Core\Retry;

use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
