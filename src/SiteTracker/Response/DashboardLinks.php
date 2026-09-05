<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardLinks
{
    public function __construct(
        public ?string $issues,
        public ?string $issueHistory,
        public ?string $resourceHistory,
        public ?string $performanceHistory,
    ) {
        Values::text($issues);
        Values::text($issueHistory);
        Values::text($resourceHistory);
        Values::text($performanceHistory);
    }
}
