<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class ResourceItem
{
    public function __construct(
        public string $url,
        public ?string $mimeType,
        public ?int $statusCode,
        public ?int $transferredBytes,
        public ?float $durationMs,
        public bool $thirdParty,
        public bool $renderBlocking,
    ) {
        Values::text($url);
        Values::text($mimeType);
        Values::finite($durationMs);
    }
}
