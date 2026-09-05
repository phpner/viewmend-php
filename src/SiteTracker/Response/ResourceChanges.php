<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class ResourceChanges
{
    /** @var list<ResourceChange> */
    public array $items;

    /**
     * @param list<ResourceChange> $items
     */
    public function __construct(
        public bool $available,
        array $items,
    ) {
        $this->items = Values::listOf($items, ResourceChange::class);
    }
}
