<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker;

use ViewMend\Internal\SiteTracker\EventSender;

final readonly class SiteTrackerClient
{
    private Events $events;

    /** @internal */
    public function __construct(EventSender $sender)
    {
        $this->events = new Events($sender);
    }

    public function events(): Events
    {
        return $this->events;
    }
}
