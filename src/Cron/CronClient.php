<?php

declare(strict_types=1);

namespace ViewMend\Cron;

use ViewMend\Cron\Response\RegistrationResult;
use ViewMend\Internal\Config\ApiToken;
use ViewMend\Internal\Cron\RegistrationSender;

final readonly class CronClient
{
    /** @internal */
    public function __construct(
        private RegistrationSender $registrations,
        private ApiToken $token,
    ) {
    }

    public function register(
        string $cron,
        string $timezone,
        string $endpointPath,
        bool $enabled = true,
    ): RegistrationResult {
        return $this->registrations->upsert(
            $cron,
            $timezone,
            $endpointPath,
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

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function verifyCallback(array $headers, string $rawBody): Callback
    {
        return CallbackVerifier::fromToken($this->token->reveal())->verify($headers, $rawBody);
    }
}
