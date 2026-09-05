<?php

declare(strict_types=1);

namespace ViewMend\Tests\Contract;

use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ViewMend\Exception\ApiException;
use ViewMend\Exception\AuthenticationException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\RateLimitException;
use ViewMend\Exception\ResourceNotFoundException;
use ViewMend\Exception\ServerException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\UnprocessableQueryException;
use ViewMend\Exception\ValidationException;
use ViewMend\Internal\Config\ClientConfig;
use ViewMend\Internal\Http\Psr18Transport;
use ViewMend\Internal\Http\RetryingTransport;
use ViewMend\Internal\Retry\ExponentialBackoffRetryPolicy;
use ViewMend\SiteTracker\SiteTrackerClient;
use ViewMend\Tests\Support\ArrayLogger;
use ViewMend\Tests\Support\FrozenClock;
use ViewMend\Tests\Support\LeakyNetworkException;
use ViewMend\Tests\Support\LeakyRequestException;
use ViewMend\Tests\Support\RecordingSleeper;
use ViewMend\Tests\Support\SequenceHttpClient;
use ViewMend\ViewMend;

final class SiteTrackerDashboardContractTest extends TestCase
{
    private const PAGE_ID = '123e4567-e89b-42d3-a456-426614174000';
    private const RUN_ID = '123e4567-e89b-42d3-a456-426614174001';

    public function testDashboardUsesIntegrationTokenAndMapsEverySection(): void
    {
        $http = new SequenceHttpClient([new Response(200, [], $this->fixture('dashboard'))]);
        $token = $this->token();
        $factory = new Psr17Factory();
        $tracker = ViewMend::withPsr18(
            token: $token,
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
            apiBaseUrl: 'https://self-hosted.example/api/v1',
        )->siteTracker('integration/id');

        self::assertCount(0, $http->requests);
        $result = $tracker->dashboard(pageId: self::PAGE_ID, device: 'mobile');

        self::assertCount(1, $http->requests);
        $request = $http->requests[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://self-hosted.example/api/v1/site-tracker/integrations/integration%2Fid/dashboard'
                . '?page_id=' . self::PAGE_ID . '&device=mobile',
            (string) $request->getUri(),
        );
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Content-Type'));
        self::assertSame(
            hash('sha256', 'Bearer ' . $token),
            hash('sha256', $request->getHeaderLine('Authorization')),
        );

        self::assertSame('Example site', $result->site->name);
        self::assertSame('123e4567-e89b-42d3-a456-426614174002', $result->site->groupId);
        self::assertSame('mobile', $result->scope->device);
        self::assertSame(self::PAGE_ID, $result->scope->page?->id);
        self::assertCount(2, $result->scope->availablePages);
        self::assertNull($result->scope->availablePages[1]->lastCheckedAt);
        self::assertSame(88, $result->summary->healthScore);
        self::assertSame(-5, $result->summary->healthScoreDelta);
        self::assertSame(2, $result->summary->trackedPages);
        self::assertSame(1, $result->summary->checkedPages);
        self::assertSame(3, $result->summary->openIssues);
        self::assertSame(1, $result->summary->criticalIssues);
        self::assertSame(self::RUN_ID, $result->latestCheck?->runId);
        self::assertSame('partial_completed', $result->latestCheck->status);
        self::assertTrue($result->latestCheck->hasComparison);
        self::assertSame('/app/tracker/history?mode=resources', $result->links->resourceHistory);
        self::assertSame('/app/tracker/history?mode=issues', $result->links->issueHistory);
        self::assertSame('/app/tracker/history?mode=performance', $result->links->performanceHistory);
        self::assertSame('/app/tracker/issues?device=mobile', $result->links->issues);
        self::assertSame(12, $result->needsAttention->total);
        self::assertCount(2, $result->needsAttention->items);
        self::assertNull($result->needsAttention->items[0]->message);
        self::assertSame('1 → 2', $result->needsAttention->items[1]->message);
        self::assertSame('critical', $result->needsAttention->items[0]->severity);
        self::assertSame(1, $result->issueTrend[0]->critical);
        self::assertSame(2, $result->issueTrend[0]->warning);
        self::assertTrue($result->transfer->available);
        self::assertSame(25000, $result->transfer->totalBytes);
        self::assertSame(4, $result->transfer->reportedRequests);
        self::assertSame(2, $result->transfer->storedRequests);
        self::assertTrue($result->transfer->truncated);
        self::assertSame(6000, $result->transfer->unattributedBytes);
        self::assertSame('snapshot_total_transfer_size', $result->transfer->source);
        self::assertSame(16000, $result->transfer->categories[0]->bytes);
        self::assertTrue($result->resourceChanges->available);
        self::assertSame(-2000, $result->resourceChanges->items[0]->deltaBytes);
        self::assertSame(200, $result->resourceChanges->items[0]->beforeStatus);
        self::assertNull($result->resourceChanges->items[1]->beforeBytes);
        self::assertSame(91, $result->performanceHistory[0]->performanceScore);
        self::assertSame(1200.5, $result->performanceHistory[0]->lcpMs);
        self::assertSame(0.03, $result->performanceHistory[0]->cls);
        self::assertSame(0.0, $result->performanceHistory[0]->totalBlockingTimeMs);
        self::assertNull($result->performanceHistory[1]->lcpMs);
        self::assertSame('2026-09-04T17:30:00+00:00', $result->generatedAt->format(DATE_ATOM));
        self::assertSame(
            $result->generatedAt->format(DATE_ATOM),
            $result->latestCheck->finishedAt?->format(DATE_ATOM),
        );
    }

    public function testEmptyDashboardIsValidAndDefaultsToDesktop(): void
    {
        $http = new SequenceHttpClient([new Response(200, [], $this->fixture('empty-dashboard'))]);
        $result = $this->sdk($http)->siteTracker('integration/id')->dashboard();

        self::assertSame('device=desktop', $http->requests[0]->getUri()->getQuery());
        self::assertNull($result->scope->page);
        self::assertSame([], $result->scope->availablePages);
        self::assertNull($result->latestCheck);
        self::assertNull($result->summary->healthScore);
        self::assertSame(0, $result->summary->trackedPages);
        self::assertNull($result->links->issues);
        self::assertNull($result->links->issueHistory);
        self::assertSame([], $result->needsAttention->items);
        self::assertSame([], $result->issueTrend);
        self::assertFalse($result->transfer->available);
        self::assertNull($result->transfer->totalBytes);
        self::assertNull($result->transfer->resourceEndpoint);
        self::assertSame([], $result->transfer->categories);
        self::assertFalse($result->resourceChanges->available);
        self::assertSame([], $result->performanceHistory);
    }

    public function testResourceInventoryUsesEncodedRunPathAndExplicitPagination(): void
    {
        $http = new SequenceHttpClient([new Response(200, [], $this->fixture('resources'))]);
        $result = $this->sdk($http)->siteTracker('integration/id')->resources(
            runId: 'run/id?part=1',
            type: 'images',
            device: 'mobile',
            page: 2,
            perPage: 2,
        );

        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame(
            'https://viewmend.com/api/v1/site-tracker/integrations/integration%2Fid/runs/'
                . 'run%2Fid%3Fpart%3D1/resources?type=images&device=mobile&page=2&per_page=2',
            (string) $http->requests[0]->getUri(),
        );
        self::assertSame('', (string) $http->requests[0]->getBody());
        self::assertSame(self::RUN_ID, $result->run->id);
        self::assertSame(self::PAGE_ID, $result->run->pageId);
        self::assertSame('https://example.com/', $result->run->pageUrl);
        self::assertSame('images', $result->type);
        self::assertSame('mobile', $result->device);
        self::assertSame(4, $result->summary->requests);
        self::assertSame(21000, $result->summary->transferredBytes);
        self::assertSame(4, $result->summary->storedRequests);
        self::assertSame(6, $result->summary->reportedRequests);
        self::assertTrue($result->summary->truncated);
        self::assertCount(2, $result->items);
        self::assertSame('https://example.com/hero.webp', $result->items[0]->url);
        self::assertSame('image/webp', $result->items[0]->mimeType);
        self::assertSame(200, $result->items[0]->statusCode);
        self::assertSame(16000, $result->items[0]->transferredBytes);
        self::assertSame(45.25, $result->items[0]->durationMs);
        self::assertFalse($result->items[0]->thirdParty);
        self::assertTrue($result->items[0]->renderBlocking);
        self::assertNull($result->items[1]->mimeType);
        self::assertNull($result->items[1]->statusCode);
        self::assertNull($result->items[1]->transferredBytes);
        self::assertNull($result->items[1]->durationMs);
        self::assertTrue($result->items[1]->thirdParty);
        self::assertFalse($result->items[1]->renderBlocking);
        self::assertSame(2, $result->pagination->page);
        self::assertSame(2, $result->pagination->perPage);
        self::assertSame(4, $result->pagination->total);
        self::assertSame(2, $result->pagination->lastPage);
        self::assertSame('123456', $result->generatedAt->format('u'));
    }

    public function testEmptyResourceInventoryAndNullableRunFieldsAreValid(): void
    {
        $http = new SequenceHttpClient([new Response(200, [], $this->fixture('empty-resources'))]);
        $result = $this->sdk($http)->siteTracker('integration-id')->resources(
            self::RUN_ID,
            'other',
            page: 3,
            perPage: 300,
        );

        self::assertNull($result->run->pageId);
        self::assertNull($result->run->finishedAt);
        self::assertSame([], $result->items);
        self::assertSame(0, $result->summary->requests);
        self::assertSame(0, $result->summary->transferredBytes);
        self::assertFalse($result->summary->truncated);
        self::assertSame(3, $result->pagination->page);
        self::assertSame(300, $result->pagination->perPage);
        self::assertSame(1, $result->pagination->lastPage);
    }

    #[DataProvider('resourceTypes')]
    public function testResourceTypesAndDefaultPagination(string $type): void
    {
        $http = new SequenceHttpClient([new Response(200, [], $this->fixture('resources'))]);
        $this->sdk($http)->siteTracker('integration-id')->resources(self::RUN_ID, $type);

        self::assertSame(
            'type=' . $type . '&device=desktop&page=1&per_page=50',
            $http->requests[0]->getUri()->getQuery(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function resourceTypes(): iterable
    {
        foreach (['images', 'javascript', 'css', 'other'] as $type) {
            yield $type => [$type];
        }
    }

    public function testFutureResponseFieldsAndStatusesRemainCompatible(): void
    {
        $body = str_replace(
            ['"partial_completed"', '"severity": "critical"', '"source": "snapshot_total_transfer_size"', '"data": {'],
            ['"future_status"', '"severity": "future_severity"', '"source": "future_source"', '"data": {"future": {},'],
            $this->fixture('dashboard'),
        );
        $http = new SequenceHttpClient([new Response(200, [], $body)]);
        $result = $this->sdk($http)->siteTracker('integration-id')->dashboard();

        self::assertSame('future_status', $result->latestCheck?->status);
        self::assertSame('future_severity', $result->needsAttention->items[0]->severity);
        self::assertSame('future_source', $result->transfer->source);
    }

    /** @param callable(SiteTrackerClient): mixed $call */
    #[DataProvider('invalidQueries')]
    public function testInvalidQueriesFailBeforeHttp(callable $call): void
    {
        $http = new SequenceHttpClient([]);

        try {
            $call($this->sdk($http)->siteTracker('integration-id'));
            self::fail('Expected invalid parameters to fail.');
        } catch (ValidationException) {
            self::assertCount(0, $http->requests);
        }
    }

    /** @return iterable<string, array{callable(SiteTrackerClient): mixed}> */
    public static function invalidQueries(): iterable
    {
        yield 'invalid page ID' => [static fn (SiteTrackerClient $c) => $c->dashboard('not-a-uuid')];
        yield 'blank page ID' => [static fn (SiteTrackerClient $c) => $c->dashboard('')];
        yield 'dashboard device' => [static fn (SiteTrackerClient $c) => $c->dashboard(device: 'tablet')];
        yield 'blank run ID' => [static fn (SiteTrackerClient $c) => $c->resources(' ', 'images')];
        yield 'invalid type' => [static fn (SiteTrackerClient $c) => $c->resources('run', 'scripts')];
        yield 'resources device' => [static fn (SiteTrackerClient $c) => $c->resources('run', 'css', 'tablet')];
        yield 'zero page' => [static fn (SiteTrackerClient $c) => $c->resources('run', 'css', page: 0)];
        yield 'negative page' => [static fn (SiteTrackerClient $c) => $c->resources('run', 'css', page: -1)];
        yield 'zero page size' => [static fn (SiteTrackerClient $c) => $c->resources('run', 'css', perPage: 0)];
        yield 'excessive page size' => [static fn (SiteTrackerClient $c) => $c->resources('run', 'css', perPage: 301)];
    }

    /** @param class-string<ApiException> $expected */
    #[DataProvider('apiErrors')]
    public function testBothEndpointsMapErrorsWithoutLeakingBodies(int $status, string $expected): void
    {
        foreach (['dashboard', 'resources'] as $endpoint) {
            $token = $this->token();
            $http = new SequenceHttpClient([
                new Response($status, ['Retry-After' => '4'], 'Internal error Authorization: Bearer ' . $token),
            ]);
            $sleeper = new RecordingSleeper();
            $logger = new ArrayLogger();
            $tracker = $this->sdk($http, $sleeper, 1, $token, $logger)->siteTracker('integration-id');

            try {
                if ($endpoint === 'dashboard') {
                    $tracker->dashboard();
                } else {
                    $tracker->resources(self::RUN_ID, 'css');
                }
                self::fail('Expected an API exception.');
            } catch (ApiException $exception) {
                self::assertInstanceOf($expected, $exception);
                self::assertSame($status, $exception->statusCode);
                self::assertNull($exception->getPrevious());
                self::assertFalse(str_contains($exception->getMessage(), $token), 'Credentials must stay private.');
                self::assertStringNotContainsString('Internal error', $exception->getMessage());
                self::assertFalse(str_contains(json_encode($logger->records, JSON_THROW_ON_ERROR), $token));
                if ($exception instanceof RateLimitException) {
                    self::assertSame('4', $exception->retryAfter);
                }
            }
            self::assertSame([], $sleeper->delays);
            self::assertCount(1, $http->requests);
        }
    }

    /** @return iterable<string, array{int, class-string<ApiException>}> */
    public static function apiErrors(): iterable
    {
        yield 'unauthorized' => [401, AuthenticationException::class];
        yield 'not found or outside group' => [404, ResourceNotFoundException::class];
        yield 'disabled' => [410, EndpointDisabledException::class];
        yield 'invalid query' => [422, UnprocessableQueryException::class];
        yield 'rate limit' => [429, RateLimitException::class];
        yield 'server failure' => [503, ServerException::class];
        yield 'unexpected success status' => [202, UnexpectedResponseException::class];
    }

    #[DataProvider('malformedFields')]
    public function testMalformedNestedFieldsFailWithoutRetry(string $fixture, string $from, string $to): void
    {
        $body = $this->fixture($fixture);
        self::assertStringContainsString($from, $body);
        $http = new SequenceHttpClient([new Response(200, [], str_replace($from, $to, $body))]);
        $sleeper = new RecordingSleeper();
        $tracker = $this->sdk($http, $sleeper)->siteTracker('integration-id');

        try {
            if ($fixture === 'resources') {
                $tracker->resources(self::RUN_ID, 'images');
            } else {
                $tracker->dashboard();
            }
            self::fail('Expected malformed response rejection.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame(200, $exception->statusCode);
            self::assertNull($exception->getPrevious());
            self::assertSame('ViewMend returned a malformed Site Tracker read response.', $exception->getMessage());
        }
        self::assertSame([], $sleeper->delays);
        self::assertCount(1, $http->requests);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function malformedFields(): iterable
    {
        yield 'missing required field' => ['dashboard', '"tracked_pages":', '"missing_tracked_pages":'];
        yield 'string counter' => ['dashboard', '"tracked_pages": 2', '"tracked_pages": "2"'];
        yield 'negative counter' => ['dashboard', '"checked_pages": 1', '"checked_pages": -1'];
        yield 'float counter' => ['dashboard', '"open_issues": 3', '"open_issues": 3.5'];
        yield 'numeric boolean' => ['dashboard', '"has_comparison": true', '"has_comparison": 1'];
        yield 'null object' => ['dashboard', '"site": {', '"site": null, "discarded": {'];
        yield 'list as object' => ['dashboard', '"site": {', '"site": [], "discarded": {'];
        yield 'empty status' => ['dashboard', '"partial_completed"', '""'];
        yield 'relative timestamp' => ['dashboard', '2026-09-04T17:30:00+00:00', 'tomorrow'];
        yield 'invalid calendar date' => ['dashboard', '2026-09-04T17:30:00+00:00', '2026-02-30T17:30:00+00:00'];
        yield 'invalid timezone' => ['dashboard', '+00:00', '+25:00'];
        yield 'missing timezone' => ['dashboard', '2026-09-04T17:30:00+00:00', '2026-09-04T17:30:00'];
        yield 'missing metadata' => ['dashboard', '"meta":', '"missing_meta":'];
        yield 'null generated timestamp' => [
            'dashboard', '"generated_at": "2026-09-04T17:30:00+00:00"', '"generated_at": null',
        ];
        yield 'string metric' => ['dashboard', '"lcp_ms": 1200.5', '"lcp_ms": "1200.5"'];
        yield 'infinite metric' => ['dashboard', '"cls": 0.03', '"cls": 1e999'];
        yield 'object as empty list' => ['empty-dashboard', '"issue_trend": []', '"issue_trend": {}'];
        yield 'object as pages list' => ['empty-dashboard', '"available_pages": []', '"available_pages": {}'];
        yield 'partial links' => ['empty-dashboard', '"links": []', '"links": {"issues": "/app"}'];
        yield 'null transfer object' => ['empty-dashboard', '"transfer": {', '"transfer": null, "discarded": {'];
        yield 'resource URL type' => ['resources', '"url": "https://example.com/hero.webp"', '"url": 123'];
        yield 'resource boolean type' => ['resources', '"third_party": false', '"third_party": "false"'];
        yield 'invalid resource list item' => ['resources', '"items": [', '"items": [null], "discarded": ['];
        yield 'resource number type' => ['resources', '"duration_ms": 45.25', '"duration_ms": "45.25"'];
        yield 'null pagination' => ['resources', '"pagination": {', '"pagination": null, "discarded": {'];
        yield 'zero pagination page' => ['resources', '"page": 2', '"page": 0'];
        yield 'excessive pagination size' => ['resources', '"per_page": 2', '"per_page": 301'];
        yield 'missing nullable field' => ['resources', '"mime_type":', '"missing_mime_type":'];
    }

    #[DataProvider('invalidDocuments')]
    public function testInvalidJsonEnvelopeFailsPredictably(string $body): void
    {
        $http = new SequenceHttpClient([new Response(200, [], $body)]);
        $this->expectException(UnexpectedResponseException::class);
        $this->sdk($http)->siteTracker('integration-id')->dashboard();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'invalid JSON' => ['{not-json'];
        yield 'null' => ['null'];
        yield 'list' => ['[]'];
        yield 'string' => ['"server detail"'];
        yield 'missing data' => ['{}'];
        yield 'null data' => ['{"data":null}'];
        yield 'list data' => ['{"data":[]}'];
    }

    public function testBothGetRequestsRetryNetwork429And503WithIdenticalUri(): void
    {
        foreach (['dashboard', 'resources'] as $endpoint) {
            $token = $this->token();
            $http = new SequenceHttpClient([
                new LeakyNetworkException('Bearer ' . $token, new Request('GET', 'https://example.com')),
                new Response(429, ['Retry-After' => '3']),
                new Response(503),
                new Response(200, [], $this->fixture($endpoint)),
            ]);
            $sleeper = new RecordingSleeper();
            $tracker = $this->sdk($http, $sleeper, 4, $token)->siteTracker('integration-id');

            if ($endpoint === 'dashboard') {
                $tracker->dashboard(self::PAGE_ID, 'mobile');
            } else {
                $tracker->resources(self::RUN_ID, 'images', page: 2);
            }

            self::assertSame([0.25, 3.0, 1.0], $sleeper->delays);
            self::assertCount(4, $http->requests);
            foreach ($http->requests as $request) {
                self::assertSame((string) $http->requests[0]->getUri(), (string) $request->getUri());
                self::assertSame('', (string) $request->getBody());
                self::assertSame('GET', $request->getMethod());
            }
        }
    }

    public function testNonNetworkTransportFailureIsNotRetried(): void
    {
        $http = new SequenceHttpClient([
            new LeakyRequestException('request error', new Request('GET', 'https://example.com')),
        ]);
        $sleeper = new RecordingSleeper();

        try {
            $this->sdk($http, $sleeper)->siteTracker('integration-id')->dashboard();
            self::fail('Expected a transport exception.');
        } catch (\ViewMend\Exception\TransportException) {
            self::assertSame([], $sleeper->delays);
            self::assertCount(1, $http->requests);
        }
    }

    private function sdk(
        SequenceHttpClient $http,
        ?RecordingSleeper $sleeper = null,
        int $maxAttempts = 3,
        ?string $token = null,
        ?ArrayLogger $logger = null,
    ): ViewMend {
        $factory = new Psr17Factory();
        $logger ??= new ArrayLogger();

        return ViewMend::fromTransport(new RetryingTransport(
            new Psr18Transport(
                new ClientConfig($token ?? $this->token(), ViewMend::PRODUCTION_API_BASE_URL),
                $http,
                $factory,
                $factory,
                $logger,
            ),
            new ExponentialBackoffRetryPolicy(
                new FrozenClock(new DateTimeImmutable('2026-09-04T17:30:00+00:00')),
                maxAttempts: $maxAttempts,
            ),
            $sleeper ?? new RecordingSleeper(),
            new NullLogger(),
        ));
    }

    private function fixture(string $name): string
    {
        $body = file_get_contents(__DIR__ . '/Fixtures/site-tracker-' . $name . '.json');
        if ($body === false) {
            self::fail('Missing Site Tracker contract fixture.');
        }

        return $body;
    }

    private function token(): string
    {
        return 'vmt_' . bin2hex(random_bytes(16));
    }
}
