<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardAttention
{
    /** @var list<AttentionItem> */
    public array $items;

    /**
     * @param list<AttentionItem> $items
     */
    public function __construct(
        public int $total,
        array $items,
    ) {
        Values::counter($total);
        $this->items = Values::listOf($items, AttentionItem::class);
    }
}
