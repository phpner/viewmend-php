<?php

declare(strict_types=1);

namespace ViewMend\Internal\Retry;

use DateTimeImmutable;
use DateTimeZone;
use ViewMend\Exception\ConfigurationException;
use ViewMend\Exception\NetworkException;
use ViewMend\Exception\TransportException;
use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\Http\HttpResponse;

final readonly class ExponentialBackoffRetryPolicy implements RetryPolicyInterface
{
    /** @var list<int> */
    private const TRANSIENT_STATUSES = [500, 502, 503, 504];

    public function __construct(
        private ClockInterface $clock = new SystemClock(),
        private int $maxAttempts = 3,
        private float $baseDelaySeconds = 0.25,
        private float $maxDelaySeconds = 30.0,
    ) {
        if ($maxAttempts < 1) {
            throw new ConfigurationException('Retry maxAttempts must be at least one.');
        }

        if ($baseDelaySeconds < 0.0 || $maxDelaySeconds < $baseDelaySeconds) {
            throw new ConfigurationException('Retry delays are invalid.');
        }
    }

    public function delayBeforeRetry(
        int $attemptsMade,
        HttpRequest $request,
        ?HttpResponse $response,
        ?TransportException $exception,
    ): ?float {
        if (!$request->retrySafe || $attemptsMade >= $this->maxAttempts) {
            return null;
        }

        if ($exception instanceof NetworkException) {
            return $this->exponentialDelay($attemptsMade);
        }

        if ($response === null) {
            return null;
        }

        if ($response->statusCode === 429) {
            return $this->retryAfterDelay($response) ?? $this->exponentialDelay($attemptsMade);
        }

        if (in_array($response->statusCode, self::TRANSIENT_STATUSES, true)) {
            return $this->exponentialDelay($attemptsMade);
        }

        return null;
    }

    private function exponentialDelay(int $attemptsMade): float
    {
        return min($this->maxDelaySeconds, $this->baseDelaySeconds * (2 ** ($attemptsMade - 1)));
    }

    private function retryAfterDelay(HttpResponse $response): ?float
    {
        $value = trim((string) $response->headerLine('Retry-After'));
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return min($this->maxDelaySeconds, (float) $value);
        }

        $date = DateTimeImmutable::createFromFormat(
            'D, d M Y H:i:s \G\M\T',
            $value,
            new DateTimeZone('GMT'),
        );
        if ($date === false) {
            return null;
        }

        $seconds = max(0, $date->getTimestamp() - $this->clock->now()->getTimestamp());

        return min($this->maxDelaySeconds, (float) $seconds);
    }
}
