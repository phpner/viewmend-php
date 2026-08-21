<?php

declare(strict_types=1);

namespace ViewMend\PluginCron;

use ViewMend\Internal\PluginCron\RegistrationSender;
use ViewMend\PluginCron\Response\RegistrationResult;

final readonly class PluginCronClient
{
    /** @internal */
    public function __construct(private RegistrationSender $registrations)
    {
    }

    public function register(
        string $cron,
        string $timezone,
        string $endpointPath,
        string $pluginId,
        ?string $pluginVersion = null,
        bool $enabled = true,
    ): RegistrationResult {
        return $this->registrations->upsert(
            $cron,
            $timezone,
            $endpointPath,
            $pluginId,
            $pluginVersion,
            $enabled,
        );
    }

    public function current(): ?RegistrationResult
    {
        return $this->registrations->current();
    }

    public function disable(): void
    {
        $this->registrations->disable();
    }
}
