<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Event;

use ViewMend\Core\Validation\Utf8;

final readonly class EventId
{
    public function __construct(public string $value)
    {
        Utf8::assertNotBlank($value, 'event_id');
        Utf8::assertMax($value, 160, 'event_id');
    }
}
