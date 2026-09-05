<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardTransfer
{
    /** @var list<TransferCategory> */
    public array $categories;

    /**
     * @param list<TransferCategory> $categories
     */
    public function __construct(
        public bool $available,
        public ?string $runId,
        public ?int $totalBytes,
        public ?int $reportedRequests,
        public int $storedRequests,
        public bool $truncated,
        public int $unattributedBytes,
        public ?string $source,
        public ?string $resourceEndpoint,
        array $categories,
    ) {
        Values::identifier($runId);
        Values::counter($totalBytes);
        Values::counter($reportedRequests);
        Values::counter($storedRequests);
        Values::counter($unattributedBytes);
        Values::identifier($source);
        Values::text($resourceEndpoint);
        $this->categories = Values::listOf($categories, TransferCategory::class);
    }
}
