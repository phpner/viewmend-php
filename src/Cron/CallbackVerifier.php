<?php

declare(strict_types=1);

namespace ViewMend\Cron;

use DateTimeImmutable;
use Exception;
use JsonException;
use ViewMend\Exception\CallbackVerificationException;

final readonly class CallbackVerifier
{
    private const MAX_AGE_SECONDS = 300;

    private function __construct(
        private string $connectionId,
        private string $signingSecret,
        private ?DateTimeImmutable $now = null,
    ) {
    }

    public static function fromToken(
        #[\SensitiveParameter] string $token,
        ?DateTimeImmutable $now = null,
    ): self {
        $matched = preg_match(
            '/^vmcron1_(pcn_[0-9a-z]{26})_([A-Za-z0-9]{64})_([A-Za-z0-9]{64})$/D',
            $token,
            $matches,
        );

        if ($matched !== 1) {
            throw new CallbackVerificationException('The Cron connection token is invalid.');
        }

        return new self($matches[1], $matches[3], $now);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function verify(array $headers, string $rawBody): Callback
    {
        $requestId = $this->header($headers, 'X-ViewMend-Request-Id');
        $timestamp = $this->header($headers, 'X-ViewMend-Timestamp');
        $signature = $this->header($headers, 'X-ViewMend-Signature');

        if ($requestId === null || $timestamp === null || $signature === null) {
            throw $this->invalid();
        }

        if (preg_match('/^[0-9]{10,12}$/D', $timestamp) !== 1) {
            throw $this->invalid();
        }

        $age = abs($this->currentTimestamp() - (int) $timestamp);
        if ($age > self::MAX_AGE_SECONDS) {
            throw new CallbackVerificationException(
                'The Cron callback timestamp is outside the allowed window.',
            );
        }

        $expected = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->signingSecret);
        if (preg_match('/^v1=[a-f0-9]{64}$/D', $signature) !== 1 || ! hash_equals($expected, $signature)) {
            throw $this->invalid();
        }

        return $this->parse($rawBody, $requestId);
    }

    /** @param array<string, string|list<string>> $headers */
    private function header(array $headers, string $wanted): ?string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, $wanted) !== 0) {
                continue;
            }

            if (is_string($value)) {
                return trim($value);
            }

            if (count($value) !== 1) {
                return null;
            }

            return trim($value[0]);
        }

        return null;
    }

    private function currentTimestamp(): int
    {
        return ($this->now ?? new DateTimeImmutable())->getTimestamp();
    }

    private function parse(string $rawBody, string $requestId): Callback
    {
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid();
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw $this->invalid();
        }

        $type = $this->requiredString($payload, 'type');
        $runId = $this->requiredString($payload, 'run_id');
        $connectionId = $this->requiredString($payload, 'connection_id');
        $jobId = $this->requiredString($payload, 'job_id');
        $scheduledAt = $this->requiredString($payload, 'scheduled_at');
        $attempt = $payload['attempt'] ?? null;

        if (
            ! in_array($type, [Callback::TYPE_VERIFICATION, Callback::TYPE_RUN], true)
            || preg_match('/^run_[0-9a-z]{26}$/D', $runId) !== 1
            || ! hash_equals($requestId, $runId)
            || ! hash_equals($this->connectionId, $connectionId)
            || preg_match('/^cron_[0-9a-z]{26}$/D', $jobId) !== 1
            || ! is_int($attempt)
            || $attempt < 1
            || ! $this->validDate($scheduledAt)
        ) {
            throw $this->invalid();
        }

        $challenge = $payload['challenge'] ?? null;
        if ($type === Callback::TYPE_VERIFICATION) {
            if (! is_string($challenge) || preg_match('/^[A-Za-z0-9]{64}$/D', $challenge) !== 1) {
                throw $this->invalid();
            }
        } elseif (array_key_exists('challenge', $payload)) {
            throw $this->invalid();
        }

        return new Callback(
            $type,
            $runId,
            $connectionId,
            $jobId,
            $scheduledAt,
            $attempt,
            is_string($challenge) ? $challenge : null,
        );
    }

    /** @param array<mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid();
        }

        return $value;
    }

    private function validDate(string $value): bool
    {
        try {
            new DateTimeImmutable($value);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    private function invalid(): CallbackVerificationException
    {
        return new CallbackVerificationException('The Cron callback could not be verified.');
    }

    /** @return array{connectionId: string, signingSecret: string} */
    public function __debugInfo(): array
    {
        return [
            'connectionId' => $this->connectionId,
            'signingSecret' => '[REDACTED]',
        ];
    }
}
