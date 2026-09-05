<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class ResourceSummary
{
    public function __construct(
        public int $requests,
        public int $transferredBytes,
        public int $storedRequests,
        public int $reportedRequests,
        public bool $truncated,
    ) {
        Values::counter($requests);
        Values::counter($transferredBytes);
        Values::counter($storedRequests);
        Values::counter($reportedRequests);
    }
}
