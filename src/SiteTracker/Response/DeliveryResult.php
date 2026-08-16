<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Exception\ValidationException;

final readonly class DeliveryResult
{
    public function __construct(
        public DeliveryId $deliveryId,
        public ViewMendEventId $eventId,
        public bool $duplicate,
        public int $affectedPages,
        public int $ignoredUrls,
        public int $checksQueued,
        public QueueStatus $queueStatus,
        public ?DateTimeImmutable $scheduledFor,
    ) {
        if ($affectedPages < 0 || $ignoredUrls < 0 || $checksQueued < 0) {
            throw new ValidationException('Delivery result counters must not be negative.');
        }
    }
}
