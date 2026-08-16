# ViewMend PHP SDK

Framework-agnostic PHP client for ViewMend APIs. The first supported module sends Site Tracker Events through API v1. The SDK uses PSR interfaces and does not depend on Laravel, WordPress, or a concrete HTTP client.

> A public license has not yet been selected. Packagist distribution and release tags will begin after licensing is finalized.

## Requirements

- PHP 8.3 or later
- a PSR-18 HTTP client
- PSR-17 request and stream factories

## Installation

Once the package is distributed through an approved Composer repository:

```bash
composer require viewmend/sdk
```

Install any compatible PSR-18/PSR-17 implementation if the application does not already provide one. For example, Guzzle provides the client and factories used below:

```bash
composer require guzzlehttp/guzzle
```

The SDK deliberately does not install Guzzle or another transport for production consumers.

## Configuration and Site Tracker Events

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use ViewMend\Core\Config\SdkConfig;
use ViewMend\SiteTracker\Event\EventType;
use ViewMend\SiteTracker\Event\SiteTrackerEvent;
use ViewMend\ViewMendClient;

require __DIR__ . '/vendor/autoload.php';

$httpClient = new GuzzleClient();
$httpFactory = new HttpFactory();

$client = ViewMendClient::create(
    config: new SdkConfig(
        apiBaseUrl: 'https://app.viewmend.com',
        apiToken: getenv('VIEWMEND_API_TOKEN') ?: throw new RuntimeException('Missing token.'),
        integration: getenv('VIEWMEND_INTEGRATION_ID') ?: throw new RuntimeException('Missing integration ID.'),
    ),
    httpClient: $httpClient,
    requestFactory: $httpFactory,
    streamFactory: $httpFactory,
);

$event = SiteTrackerEvent::builder(
    eventId: 'deploy-2026-08-16-abc123',
    eventType: EventType::Deployment,
    title: 'Homepage deployed',
)
    ->occurredAt(new DateTimeImmutable('now'))
    ->siteUrl('https://example.com')
    ->pageUrls('https://example.com/', 'https://example.com/pricing')
    ->environment('production')
    ->description('Published release abc123.')
    ->referenceUrl('https://github.com/example/project/actions/runs/123')
    ->changedFields('content', 'metadata')
    ->metadata(['commit' => 'abc123'])
    ->build();

$result = $client->siteTracker()->events()->send($event);

printf(
    "Delivery %s: event %s is %s (%s)\n",
    $result->deliveryId->value,
    $result->eventId->value,
    $result->duplicate ? 'a duplicate' : 'accepted',
    $result->queueStatus->value,
);
```

Use an `event_id` that is unique and stable for the originating event. If a safe retry occurs, the SDK sends the identical serialized payload and the same `event_id`; the server returns HTTP 200 with `duplicate=true` when it has already accepted that identifier.

`QueueStatus` preserves unknown future values. Consumers may use `isKnown()` for display logic but should retain and log the raw `value` instead of treating a new status as a broken response.

## Dependency injection

`ViewMendClient::create()` accepts any implementations of:

- `Psr\Http\Client\ClientInterface`
- `Psr\Http\Message\RequestFactoryInterface`
- `Psr\Http\Message\StreamFactoryInterface`
- optionally `Psr\Log\LoggerInterface`

The default logger is `Psr\Log\NullLogger`. The default retry policy makes at most three total attempts for explicitly retry-safe calls. A custom `RetryPolicyInterface`, `ClockInterface`, and `SleeperInterface` can be injected for application policy or deterministic testing.

## Error handling

All SDK failures extend `ViewMend\Core\Exception\ViewMendException`. Significant API statuses have dedicated types:

- `AuthenticationException` for 401
- `EndpointDisabledException` for 410
- `PayloadTooLargeException` for 413
- `UnprocessableEventException` for 422
- `RateLimitException` for exhausted 429 responses
- `ServerException` for exhausted 5xx responses
- `NetworkException` (a `TransportException`) for exhausted PSR-18 network failures
- `TransportException` for other non-retryable PSR-18 transport failures
- `UnexpectedResponseException` for unexpected status codes or malformed successful JSON

Exception messages and SDK log context do not include authorization headers, the API token, or raw response bodies.

## Development

```bash
composer validate --no-check-publish
composer test
composer analyse
composer cs:check
composer quality
```

Tests use a mock PSR-18 client and never send network traffic or perform real sleeps. Architectural decisions and public boundaries are recorded in [docs/architecture.md](docs/architecture.md).
