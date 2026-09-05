<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardSummary
{
    public function __construct(
        public ?int $healthScore,
        public ?int $healthScoreDelta,
        public int $trackedPages,
        public int $checkedPages,
        public int $openIssues,
        public int $criticalIssues,
    ) {
        Values::counter($trackedPages);
        Values::counter($checkedPages);
        Values::counter($openIssues);
        Values::counter($criticalIssues);
    }
}
