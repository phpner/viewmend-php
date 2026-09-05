<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class AttentionItem
{
    public function __construct(
        public string $id,
        public string $source,
        public string $severity,
        public string $title,
        public ?string $message,
        public string $status,
        public string $pageId,
        public ?string $pageUrl,
        public ?DateTimeImmutable $occurredAt,
    ) {
        Values::identifier($id);
        Values::identifier($source);
        Values::identifier($severity);
        Values::text($title);
        Values::text($message);
        Values::identifier($status);
        Values::identifier($pageId);
        Values::text($pageUrl);
    }
}
