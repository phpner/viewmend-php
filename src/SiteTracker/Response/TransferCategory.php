<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class TransferCategory
{
    public function __construct(
        public string $key,
        public int $bytes,
        public int $requests,
    ) {
        Values::identifier($key);
        Values::counter($bytes);
        Values::counter($requests);
    }
}
