<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardSite
{
    public function __construct(
        public string $groupId,
        public string $name,
    ) {
        Values::identifier($groupId);
        Values::text($name);
    }
}
