<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker;

final readonly class SiteTrackerClient
{
    public function __construct(private EventsClient $events)
    {
    }

    public function events(): EventsClient
    {
        return $this->events;
    }
}
