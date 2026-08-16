<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ViewMend\Exception\NetworkException;
use ViewMend\Exception\TransportException;
use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\Http\HttpResponse;
use ViewMend\Internal\Retry\ExponentialBackoffRetryPolicy;
use ViewMend\Tests\Support\FrozenClock;

final class RetryPolicyTest extends TestCase
{
    #[DataProvider('transientStatuses')]
    public function testRetriesDocumentedTransientStatuses(int $status): void
    {
        $policy = $this->policy();

        self::assertSame(0.25, $policy->delayBeforeRetry(
            1,
            $this->request(),
            new HttpResponse($status, ''),
            null,
        ));
    }

    /** @return iterable<string, array{int}> */
    public static function transientStatuses(): iterable
    {
        yield '500' => [500];
        yield '502' => [502];
        yield '503' => [503];
        yield '504' => [504];
    }

    #[DataProvider('terminalStatuses')]
    public function testDoesNotRetryTerminalStatuses(int $status): void
    {
        $policy = $this->policy();

        self::assertNull($policy->delayBeforeRetry(
            1,
            $this->request(),
            new HttpResponse($status, ''),
            null,
        ));
    }

    /** @return iterable<string, array{int}> */
    public static function terminalStatuses(): iterable
    {
        yield 'auth' => [401];
        yield 'disabled' => [410];
        yield 'too-large' => [413];
        yield 'unprocessable' => [422];
        yield 'not-implemented' => [501];
    }

    public function testNetworkFailuresUseBoundedExponentialDelay(): void
    {
        $policy = $this->policy();
        $exception = new NetworkException('safe');

        self::assertSame(0.25, $policy->delayBeforeRetry(1, $this->request(), null, $exception));
        self::assertSame(0.5, $policy->delayBeforeRetry(2, $this->request(), null, $exception));
        self::assertNull($policy->delayBeforeRetry(3, $this->request(), null, $exception));
    }

    public function testRetryAfterDeltaSecondsIsHonoured(): void
    {
        $policy = $this->policy();
        $response = new HttpResponse(429, '', ['Retry-After' => '7']);

        self::assertSame(7.0, $policy->delayBeforeRetry(1, $this->request(), $response, null));
    }

    public function testNonNetworkTransportFailureIsNotRetried(): void
    {
        self::assertNull($this->policy()->delayBeforeRetry(
            1,
            $this->request(),
            null,
            new TransportException('terminal'),
        ));
    }

    public function testRetryAfterHttpDateUsesInjectedClock(): void
    {
        $policy = $this->policy();
        $response = new HttpResponse(429, '', [
            'Retry-After' => 'Sun, 16 Aug 2026 12:00:12 GMT',
        ]);

        self::assertSame(12.0, $policy->delayBeforeRetry(1, $this->request(), $response, null));
    }

    public function testUnsafeRequestIsNeverRetried(): void
    {
        $request = new HttpRequest('POST', '/unsafe', retrySafe: false);

        self::assertNull($this->policy()->delayBeforeRetry(
            1,
            $request,
            new HttpResponse(503, ''),
            null,
        ));
    }

    private function policy(): ExponentialBackoffRetryPolicy
    {
        return new ExponentialBackoffRetryPolicy(
            new FrozenClock(new DateTimeImmutable('2026-08-16T12:00:00+00:00')),
            maxAttempts: 3,
            baseDelaySeconds: 0.25,
            maxDelaySeconds: 30.0,
        );
    }

    private function request(): HttpRequest
    {
        return new HttpRequest('POST', '/events', retrySafe: true);
    }
}
