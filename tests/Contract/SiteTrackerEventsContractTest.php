<?php

declare(strict_types=1);

namespace ViewMend\Tests\Contract;

use DateTimeImmutable;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ViewMend\Exception\ApiException;
use ViewMend\Exception\AuthenticationException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\PayloadTooLargeException;
use ViewMend\Exception\RateLimitException;
use ViewMend\Exception\ServerException;
use ViewMend\Exception\TransportException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\UnprocessableEventException;
use ViewMend\Internal\Config\ClientConfig;
use ViewMend\Internal\Http\Psr18Transport;
use ViewMend\Internal\Http\RetryingTransport;
use ViewMend\Internal\Retry\ExponentialBackoffRetryPolicy;
use ViewMend\Internal\Retry\RetryPolicyInterface;
use ViewMend\Internal\Retry\SleeperInterface;
use ViewMend\SiteTracker\Response\QueueStatus;
use ViewMend\Tests\Support\ArrayLogger;
use ViewMend\Tests\Support\FrozenClock;
use ViewMend\Tests\Support\LeakyNetworkException;
use ViewMend\Tests\Support\LeakyRequestException;
use ViewMend\Tests\Support\RecordingSleeper;
use ViewMend\Tests\Support\SequenceHttpClient;
use ViewMend\ViewMend;

final class SiteTrackerEventsContractTest extends TestCase
{
    /** @throws JsonException */
    public function testSendsExactSiteTrackerV1RequestAndParsesAcceptedResponse(): void
    {
        $http = new SequenceHttpClient([$this->successResponse()]);
        $token = $this->token();
        $client = $this->sdk($http, $token);

        $result = $client
            ->siteTracker('integration/id')
            ->events()
            ->deployment('deploy-123', 'Homepage deployed')
            ->occurredAt(new DateTimeImmutable('2026-08-16T12:00:00+00:00'))
            ->site('https://example.com')
            ->page('https://example.com/')
            ->page('https://example.com/pricing')
            ->environment('production')
            ->description('Published the homepage.')
            ->reference('https://example.com/releases/123')
            ->contentChanged()
            ->metadataChanged()
            ->metadata(['commit' => 'abc123'])
            ->send();

        self::assertCount(1, $http->requests);
        $request = $http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://viewmend.com/api/v1/site-tracker/integrations/integration%2Fid/events',
            (string) $request->getUri(),
        );
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            hash('sha256', 'Bearer ' . $token),
            hash('sha256', $request->getHeaderLine('Authorization')),
        );
        self::assertFalse($request->hasHeader('X-ViewMend-Timestamp'));
        self::assertSame([
            'event_id' => 'deploy-123',
            'event_type' => 'deployment',
            'title' => 'Homepage deployed',
            'occurred_at' => '2026-08-16T12:00:00+00:00',
            'site_url' => 'https://example.com',
            'page_urls' => ['https://example.com/', 'https://example.com/pricing'],
            'environment' => 'production',
            'description' => 'Published the homepage.',
            'reference_url' => 'https://example.com/releases/123',
            'changed_fields' => ['content', 'metadata'],
            'metadata' => ['commit' => 'abc123'],
        ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));

        self::assertSame('delivery-123', $result->deliveryId->value);
        self::assertSame('internal-event-123', $result->eventId->value);
        self::assertFalse($result->duplicate);
        self::assertSame(2, $result->affectedPages);
        self::assertSame(1, $result->ignoredUrls);
        self::assertSame(0, $result->checksQueued);
        self::assertTrue($result->queueStatus->is(QueueStatus::QUEUED));
        self::assertSame('2026-08-16T12:05:00+00:00', $result->scheduledFor?->format(DATE_ATOM));
        self::assertTrue($http->isExhausted());
    }

    /** @throws JsonException */
    public function testSerializesAnExplicitCustomChangedField(): void
    {
        $http = new SequenceHttpClient([$this->successResponse()]);

        $this->sdk($http, $this->token())
            ->siteTracker('integration-id')
            ->events()
            ->custom('custom-1', 'Schema changed')
            ->customFieldChanged('product_schema')
            ->send();

        $payload = json_decode(
            (string) $http->requests[0]->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (!is_array($payload)) {
            self::fail('Expected the request body to contain a JSON object.');
        }

        self::assertSame(['product_schema'], $payload['changed_fields'] ?? null);
    }

    public function testParsesDuplicateResponseWithoutCreatingASecondClientEvent(): void
    {
        $http = new SequenceHttpClient([$this->successResponse(200, ['duplicate' => true])]);

        $result = $this->sdk($http, $this->token())
            ->siteTracker('integration/id')
            ->events()
            ->custom('stable-id', 'Same event')
            ->send();

        self::assertTrue($result->duplicate);
        self::assertSame('internal-event-123', $result->eventId->value);
    }

    public function testAdvancedPsr18InjectionCanOverrideApiBaseUrl(): void
    {
        $http = new SequenceHttpClient([$this->successResponse()]);
        $factory = new Psr17Factory();
        $client = ViewMend::withPsr18(
            token: $this->token(),
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
            apiBaseUrl: 'https://self-hosted.example/api/v1',
        );

        $client->siteTracker('advanced/id')->events()
            ->contentUpdate('content-1', 'Content updated')
            ->send();

        self::assertSame(
            'https://self-hosted.example/api/v1/site-tracker/integrations/advanced%2Fid/events',
            (string) $http->requests[0]->getUri(),
        );
    }

    #[DataProvider('semanticEventMethods')]
    public function testSemanticEventMethodsMapToExactApiTypes(string $method, string $expectedType): void
    {
        $http = new SequenceHttpClient([$this->successResponse()]);
        $events = $this->sdk($http, $this->token())
            ->siteTracker('integration-id')
            ->events();

        $pending = match ($method) {
            'deployment' => $events->deployment('event-id', 'Event title'),
            'contentUpdate' => $events->contentUpdate('event-id', 'Event title'),
            'pluginUpdate' => $events->pluginUpdate('event-id', 'Event title'),
            'themeUpdate' => $events->themeUpdate('event-id', 'Event title'),
            'cacheCleared' => $events->cacheCleared('event-id', 'Event title'),
            'trackingScriptChange' => $events->trackingScriptChange('event-id', 'Event title'),
            'maintenance' => $events->maintenance('event-id', 'Event title'),
            'custom' => $events->custom('event-id', 'Event title'),
            default => self::fail('Unexpected semantic event method.'),
        };

        $pending->send();
        $payload = json_decode((string) $http->requests[0]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            self::fail('Expected a JSON object payload.');
        }

        self::assertSame($expectedType, $payload['event_type'] ?? null);
    }

    /** @return iterable<string, array{string, string}> */
    public static function semanticEventMethods(): iterable
    {
        yield 'deployment' => ['deployment', 'deployment'];
        yield 'content update' => ['contentUpdate', 'content_update'];
        yield 'plugin update' => ['pluginUpdate', 'plugin_update'];
        yield 'theme update' => ['themeUpdate', 'theme_update'];
        yield 'cache cleared' => ['cacheCleared', 'cache_cleared'];
        yield 'tracking script change' => ['trackingScriptChange', 'tracking_script_change'];
        yield 'maintenance' => ['maintenance', 'maintenance'];
        yield 'custom' => ['custom', 'custom'];
    }

    /** @throws JsonException */
    public function testMinimalEventOmitsEveryOptionalField(): void
    {
        $http = new SequenceHttpClient([$this->successResponse(202, ['scheduled_for' => null])]);

        $result = $this->sdk($http, $this->token())
            ->siteTracker('integration/id')
            ->events()
            ->custom('minimal-id', 'Minimal event')
            ->send();

        self::assertSame([
            'event_id' => 'minimal-id',
            'event_type' => 'custom',
            'title' => 'Minimal event',
        ], json_decode((string) $http->requests[0]->getBody(), true, 512, JSON_THROW_ON_ERROR));
        self::assertNull($result->scheduledFor);
    }

    public function testUnknownQueueStatusIsForwardCompatible(): void
    {
        $http = new SequenceHttpClient([$this->successResponse(202, [
            'queue_status' => 'waiting_for_regional_capacity',
        ])]);

        $result = $this->sdk($http, $this->token())
            ->siteTracker('integration/id')
            ->events()
            ->custom('event-1', 'Future state')
            ->send();

        self::assertSame('waiting_for_regional_capacity', $result->queueStatus->value);
        self::assertFalse($result->queueStatus->isKnown());
    }

    public function testRetryAfterIsHonouredAndSerializedEventIdDoesNotChange(): void
    {
        $http = new SequenceHttpClient([
            new Response(429, ['Retry-After' => '3'], '{"message":"slow down"}'),
            $this->successResponse(),
        ]);
        $sleeper = new RecordingSleeper();
        $policy = $this->retryPolicy(maxAttempts: 3);

        $this->sdk($http, $this->token(), retryPolicy: $policy, sleeper: $sleeper)
            ->siteTracker('integration/id')
            ->events()
            ->deployment('idempotent-event', 'Safe retry')
            ->send();

        self::assertSame([3.0], $sleeper->delays);
        self::assertCount(2, $http->requests);
        self::assertSame((string) $http->requests[0]->getBody(), (string) $http->requests[1]->getBody());
        self::assertStringContainsString('"event_id":"idempotent-event"', (string) $http->requests[1]->getBody());
    }

    public function testTransientServerFailureIsRetried(): void
    {
        $http = new SequenceHttpClient([
            new Response(503, [], '{"message":"temporary"}'),
            $this->successResponse(),
        ]);
        $sleeper = new RecordingSleeper();

        $this->sdk(
            $http,
            $this->token(),
            retryPolicy: $this->retryPolicy(maxAttempts: 3),
            sleeper: $sleeper,
        )->siteTracker('integration/id')->events()
            ->maintenance('event-503', 'Maintenance complete')
            ->send();

        self::assertSame([0.25], $sleeper->delays);
        self::assertCount(2, $http->requests);
    }

    public function testSafeNetworkFailureIsRetriedWithoutLeakingOriginalException(): void
    {
        $leak = implode('', ['Bearer ', 'vmt_', bin2hex(random_bytes(8))]);
        $http = new SequenceHttpClient([
            new LeakyNetworkException($leak, new Request('POST', 'https://example.com')),
            $this->successResponse(),
        ]);
        $sleeper = new RecordingSleeper();

        $this->sdk(
            $http,
            $this->token(),
            retryPolicy: $this->retryPolicy(maxAttempts: 3),
            sleeper: $sleeper,
        )->siteTracker('integration/id')->events()
            ->custom('event-network', 'Network retry')
            ->send();

        self::assertSame([0.25], $sleeper->delays);
        self::assertCount(2, $http->requests);
    }

    public function testPsrRequestFailureIsNotRetried(): void
    {
        $http = new SequenceHttpClient([
            new LeakyRequestException('invalid request', new Request('POST', 'https://example.com')),
            $this->successResponse(),
        ]);
        $sleeper = new RecordingSleeper();

        try {
            $this->sdk(
                $http,
                $this->token(),
                retryPolicy: $this->retryPolicy(maxAttempts: 3),
                sleeper: $sleeper,
            )->siteTracker('integration/id')->events()
                ->custom('event-request', 'Request failure')
                ->send();
            self::fail('Expected the request failure to throw.');
        } catch (TransportException $exception) {
            self::assertSame('The ViewMend HTTP request could not be completed.', $exception->getMessage());
        }

        self::assertSame([], $sleeper->delays);
        self::assertCount(1, $http->requests);
    }

    /** @param class-string<ApiException> $expectedClass */
    #[DataProvider('apiErrors')]
    public function testMapsSignificantApiErrorsWithoutRetry(int $status, string $expectedClass): void
    {
        $http = new SequenceHttpClient([
            new Response($status, [
                'X-ViewMend-Delivery' => 'delivery-error',
                'Retry-After' => '4',
            ], '{"message":"server detail"}'),
        ]);

        try {
            $this->sdk(
                $http,
                $this->token(),
                retryPolicy: $this->retryPolicy(maxAttempts: 1),
                sleeper: new RecordingSleeper(),
            )->siteTracker('integration/id')->events()
                ->custom('event-error', 'Error mapping')
                ->send();
            self::fail('Expected the API response to throw.');
        } catch (ApiException $exception) {
            self::assertInstanceOf($expectedClass, $exception);
            self::assertSame($status, $exception->statusCode);
            self::assertSame('delivery-error', $exception->deliveryId);
            self::assertStringNotContainsString('server detail', $exception->getMessage());

            if ($exception instanceof RateLimitException) {
                self::assertSame('4', $exception->retryAfter);
            }
        }

        self::assertCount(1, $http->requests);
    }

    /** @return iterable<string, array{int, class-string<ApiException>}> */
    public static function apiErrors(): iterable
    {
        yield 'authentication' => [401, AuthenticationException::class];
        yield 'disabled endpoint' => [410, EndpointDisabledException::class];
        yield 'payload too large' => [413, PayloadTooLargeException::class];
        yield 'invalid or inapplicable' => [422, UnprocessableEventException::class];
        yield 'rate limited after retries' => [429, RateLimitException::class];
        yield 'server failure after retries' => [500, ServerException::class];
    }

    public function testMalformedJsonSuccessResponseFailsPredictably(): void
    {
        $http = new SequenceHttpClient([
            new Response(202, ['X-ViewMend-Delivery' => 'delivery-123'], '{not-json'),
        ]);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('malformed Site Tracker response');

        $this->sdk($http, $this->token())
            ->siteTracker('integration/id')
            ->events()
            ->custom('event-json', 'Malformed response')
            ->send();
    }

    public function testDeliveryHeaderMustMatchTypedBody(): void
    {
        $http = new SequenceHttpClient([
            $this->successResponse(202, [], ['X-ViewMend-Delivery' => 'different-delivery']),
        ]);

        $this->expectException(UnexpectedResponseException::class);
        $this->sdk($http, $this->token())
            ->siteTracker('integration/id')
            ->events()
            ->custom('event-header', 'Header mismatch')
            ->send();
    }

    /** @throws JsonException */
    public function testTokenIsRedactedFromExceptionsLogsAndDebugOutput(): void
    {
        $token = $this->token();
        $logger = new ArrayLogger();
        $http = new SequenceHttpClient([
            new LeakyNetworkException(
                'Authorization: Bearer ' . $token,
                new Request('POST', 'https://example.com'),
            ),
        ]);
        $config = new ClientConfig($token, ViewMend::PRODUCTION_API_BASE_URL);

        try {
            $this->sdk(
                $http,
                $token,
                logger: $logger,
                retryPolicy: $this->retryPolicy(maxAttempts: 1),
                sleeper: new RecordingSleeper(),
            )->siteTracker('integration/id')->events()
                ->custom('event-redaction', 'Redaction')
                ->send();
            self::fail('Expected the network failure to throw.');
        } catch (TransportException $exception) {
            $observableOutput = implode("\n", [
                $exception->getMessage(),
                json_encode($logger->records, JSON_THROW_ON_ERROR),
                print_r($config, true),
            ]);

            self::assertStringNotContainsString($token, $observableOutput);
            self::assertNull($exception->getPrevious());
        }
    }

    private function sdk(
        SequenceHttpClient $http,
        string $token,
        ?LoggerInterface $logger = null,
        ?RetryPolicyInterface $retryPolicy = null,
        ?SleeperInterface $sleeper = null,
    ): ViewMend {
        $factory = new Psr17Factory();

        if ($retryPolicy === null && $sleeper === null) {
            return ViewMend::withPsr18(
                token: $token,
                httpClient: $http,
                requestFactory: $factory,
                streamFactory: $factory,
                logger: $logger,
            );
        }

        $logger ??= new NullLogger();
        $retryPolicy ??= $this->retryPolicy(maxAttempts: 3);
        $sleeper ??= new RecordingSleeper();
        $transport = new RetryingTransport(
            new Psr18Transport(
                new ClientConfig($token, ViewMend::PRODUCTION_API_BASE_URL),
                $http,
                $factory,
                $factory,
                $logger,
            ),
            $retryPolicy,
            $sleeper,
            $logger,
        );

        return ViewMend::fromTransport($transport);
    }

    private function retryPolicy(int $maxAttempts): ExponentialBackoffRetryPolicy
    {
        return new ExponentialBackoffRetryPolicy(
            new FrozenClock(new DateTimeImmutable('2026-08-16T12:00:00+00:00')),
            maxAttempts: $maxAttempts,
            baseDelaySeconds: 0.25,
            maxDelaySeconds: 30.0,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<string, string|list<string>> $headers
     */
    private function successResponse(int $status = 202, array $overrides = [], array $headers = []): Response
    {
        $body = array_replace([
            'delivery_id' => 'delivery-123',
            'ok' => true,
            'event_id' => 'internal-event-123',
            'duplicate' => false,
            'affected_pages' => 2,
            'ignored_urls' => 1,
            'checks_queued' => 0,
            'queue_status' => 'queued',
            'scheduled_for' => '2026-08-16T12:05:00+00:00',
        ], $overrides);

        return new Response(
            $status,
            array_replace(['X-ViewMend-Delivery' => 'delivery-123'], $headers),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    private function token(): string
    {
        return 'vmt_' . bin2hex(random_bytes(16));
    }
}
