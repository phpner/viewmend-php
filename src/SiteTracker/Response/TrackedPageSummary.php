<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class TrackedPageSummary
{
    public function __construct(
        public string $id,
        public string $url,
        public ?DateTimeImmutable $lastCheckedAt,
    ) {
        Values::identifier($id);
        Values::text($url);
    }
}
