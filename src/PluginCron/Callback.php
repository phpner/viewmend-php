<?php

declare(strict_types=1);

namespace ViewMend\PluginCron;

use JsonException;
use ViewMend\Exception\CallbackVerificationException;

final readonly class Callback
{
    public const TYPE_VERIFICATION = 'plugin_cron.verification';

    public const TYPE_RUN = 'plugin_cron.run';

    public function __construct(
        public string $type,
        public string $runId,
        public string $connectionId,
        public string $jobId,
        public string $scheduledAt,
        public int $attempt,
        public ?string $challenge,
    ) {
    }

    public function isVerification(): bool
    {
        return $this->type === self::TYPE_VERIFICATION;
    }

    public function isRun(): bool
    {
        return $this->type === self::TYPE_RUN;
    }

    public function verificationResponseBody(): string
    {
        if (! $this->isVerification() || $this->challenge === null) {
            throw new CallbackVerificationException('Only verification callbacks have a challenge response.');
        }

        try {
            return json_encode(
                ['challenge' => $this->challenge],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            throw new CallbackVerificationException('The verification response could not be encoded.');
        }
    }
}
