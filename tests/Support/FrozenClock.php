<?php

declare(strict_types=1);

namespace ViewMend\Tests\Support;

use DateTimeImmutable;
use ViewMend\Internal\Retry\ClockInterface;

final readonly class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
