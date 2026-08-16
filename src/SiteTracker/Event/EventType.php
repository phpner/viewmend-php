<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Event;

enum EventType: string
{
    case Deployment = 'deployment';
    case ContentUpdate = 'content_update';
    case PluginUpdate = 'plugin_update';
    case ThemeUpdate = 'theme_update';
    case CacheCleared = 'cache_cleared';
    case TrackingScriptChange = 'tracking_script_change';
    case Maintenance = 'maintenance';
    case Custom = 'custom';
}
