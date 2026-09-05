<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class PerformancePoint
{
    public function __construct(
        public string $runId,
        public ?DateTimeImmutable $finishedAt,
        public ?int $performanceScore,
        public ?float $lcpMs,
        public ?float $cls,
        public ?float $totalBlockingTimeMs,
    ) {
        Values::identifier($runId);
        Values::finite($lcpMs);
        Values::finite($cls);
        Values::finite($totalBlockingTimeMs);
    }
}
