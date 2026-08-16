<?php

declare(strict_types=1);

namespace ViewMend\Tests\Support;

use ViewMend\Internal\Retry\SleeperInterface;

final class RecordingSleeper implements SleeperInterface
{
    /** @var list<float> */
    public array $delays = [];

    public function sleep(float $seconds): void
    {
        $this->delays[] = $seconds;
    }
}
