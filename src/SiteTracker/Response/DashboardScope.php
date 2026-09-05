<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardScope
{
    /** @var list<TrackedPageSummary> */
    public array $availablePages;

    /**
     * @param list<TrackedPageSummary> $availablePages
     */
    public function __construct(
        public string $device,
        public ?TrackedPageSummary $page,
        array $availablePages,
    ) {
        Values::identifier($device);
        $this->availablePages = Values::listOf($availablePages, TrackedPageSummary::class);
    }
}
