<?php

declare(strict_types=1);

namespace ViewMend\Cron\Response;

use DateTimeImmutable;

final readonly class RegistrationResult
{
    public function __construct(
        public string $id,
        public string $connectionId,
        public string $domain,
        public string $endpointPath,
        public string $endpointUrl,
        public string $method,
        public string $cron,
        public string $timezone,
        public bool $enabled,
        public string $status,
        public ?DateTimeImmutable $verifiedAt,
        public ?DateTimeImmutable $nextRunAt,
        public ?DateTimeImmutable $lastRunAt,
        public int $consecutiveFailures,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }
}
