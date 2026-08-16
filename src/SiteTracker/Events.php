<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker;

use ViewMend\Internal\SiteTracker\Event\EventType;
use ViewMend\Internal\SiteTracker\Event\SiteTrackerEvent;
use ViewMend\Internal\SiteTracker\EventSender;

final readonly class Events
{
    /** @internal */
    public function __construct(private EventSender $sender)
    {
    }

    public function deployment(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::Deployment, $title);
    }

    public function contentUpdate(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::ContentUpdate, $title);
    }

    public function pluginUpdate(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::PluginUpdate, $title);
    }

    public function themeUpdate(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::ThemeUpdate, $title);
    }

    public function cacheCleared(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::CacheCleared, $title);
    }

    public function trackingScriptChange(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::TrackingScriptChange, $title);
    }

    public function maintenance(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::Maintenance, $title);
    }

    public function custom(string $id, string $title): PendingEvent
    {
        return $this->event($id, EventType::Custom, $title);
    }

    private function event(string $id, EventType $type, string $title): PendingEvent
    {
        return new PendingEvent(
            $this->sender,
            SiteTrackerEvent::create($id, $type, $title),
        );
    }
}
