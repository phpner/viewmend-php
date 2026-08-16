<?php

declare(strict_types=1);

namespace ViewMend\Internal\Retry;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
