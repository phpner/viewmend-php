<?php

declare(strict_types=1);

namespace ViewMend\Internal\Retry;

interface SleeperInterface
{
    public function sleep(float $seconds): void;
}
