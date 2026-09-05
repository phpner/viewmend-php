<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardCheck
{
    public function __construct(
        public string $runId,
        public string $status,
        public ?DateTimeImmutable $finishedAt,
        public bool $hasComparison,
    ) {
        Values::identifier($runId);
        Values::identifier($status);
    }
}
