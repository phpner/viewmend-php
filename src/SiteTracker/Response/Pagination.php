<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class Pagination
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {
        Values::positive($page);
        Values::pageSize($perPage);
        Values::counter($total);
        Values::positive($lastPage);
    }
}
