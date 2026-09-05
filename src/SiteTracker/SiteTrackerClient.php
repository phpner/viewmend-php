<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker;

use ViewMend\Internal\SiteTracker\EventSender;
use ViewMend\Internal\SiteTracker\Dashboard\Reader;
use ViewMend\SiteTracker\Response\DashboardResult;
use ViewMend\SiteTracker\Response\ResourcesResult;

final readonly class SiteTrackerClient
{
    private Events $events;

    /** @internal */
    public function __construct(EventSender $sender, private Reader $reader)
    {
        $this->events = new Events($sender);
    }

    public function events(): Events
    {
        return $this->events;
    }

    public function dashboard(?string $pageId = null, string $device = 'desktop'): DashboardResult
    {
        return $this->reader->dashboard($pageId, $device);
    }

    public function resources(
        string $runId,
        string $type,
        string $device = 'desktop',
        int $page = 1,
        int $perPage = 50,
    ): ResourcesResult {
        return $this->reader->resources($runId, $type, $device, $page, $perPage);
    }
}
