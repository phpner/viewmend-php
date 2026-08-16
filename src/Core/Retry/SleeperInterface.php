<?php

declare(strict_types=1);

namespace ViewMend\Core\Retry;

interface SleeperInterface
{
    public function sleep(float $seconds): void;
}
