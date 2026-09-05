<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class IssueTrendPoint
{
    public function __construct(
        public string $runId,
        public ?DateTimeImmutable $finishedAt,
        public int $critical,
        public int $warning,
    ) {
        Values::identifier($runId);
        Values::counter($critical);
        Values::counter($warning);
    }
}
