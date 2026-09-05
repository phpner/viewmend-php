<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class ResourcesResult
{
    /** @var list<ResourceItem> */
    public array $items;

    /**
     * @param list<ResourceItem> $items
     */
    public function __construct(
        public ResourceRun $run,
        public string $type,
        public string $device,
        public ResourceSummary $summary,
        array $items,
        public Pagination $pagination,
        public DateTimeImmutable $generatedAt,
    ) {
        Values::identifier($type);
        Values::identifier($device);
        $this->items = Values::listOf($items, ResourceItem::class);
    }
}
