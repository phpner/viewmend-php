<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class ResourceChange
{
    public function __construct(
        public string $changeType,
        public string $resourceType,
        public string $url,
        public ?string $mimeType,
        public ?int $beforeBytes,
        public ?int $afterBytes,
        public ?int $deltaBytes,
        public ?int $beforeStatus,
        public ?int $afterStatus,
    ) {
        Values::identifier($changeType);
        Values::identifier($resourceType);
        Values::text($url);
        Values::text($mimeType);
    }
}
