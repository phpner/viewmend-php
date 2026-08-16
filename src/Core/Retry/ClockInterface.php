<?php

declare(strict_types=1);

namespace ViewMend\Core\Retry;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
