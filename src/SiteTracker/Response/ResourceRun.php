<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class ResourceRun
{
    public function __construct(
        public string $id,
        public ?string $pageId,
        public string $pageUrl,
        public ?DateTimeImmutable $finishedAt,
    ) {
        Values::identifier($id);
        Values::identifier($pageId);
        Values::text($pageUrl);
    }
}
