# ViewMend PHP SDK

The ViewMend PHP SDK is the official framework-agnostic PHP client for ViewMend APIs. It provides shared authentication and configuration, a production-ready HTTP transport, safe retries, typed errors, and isolated product modules for integrating PHP applications with the [ViewMend website monitoring platform](https://viewmend.com/).

## Available modules

### Site Tracker Events

Site Tracker Events is the first and currently only available SDK module. PHP applications can send deployment events, content updates, cache clears, and maintenance activity to ViewMend, which connects that change context with subsequent checks of tracked pages in the Events and Timeline workflow.

Learn more about [ViewMend Site Tracker for website change monitoring](https://viewmend.com/site-tracker).

> The package is prepared for its first public release and is not yet published on Packagist.

## Installation

After the first approved release is published on Packagist, install it with:

```bash
composer require viewmend/sdk
```

Guzzle is included as the SDK's default HTTP transport; application code does not need to install, configure, or import it.

## Quick Start

This example uses Site Tracker Events, the first available product module:

```php
<?php

declare(strict_types=1);

use ViewMend\ViewMend;

require __DIR__ . '/vendor/autoload.php';

$token = getenv('VIEWMEND_API_TOKEN');
if ($token === false || trim($token) === '') {
    throw new \RuntimeException('VIEWMEND_API_TOKEN is required.');
}
$token = trim($token);

$integrationId = getenv('VIEWMEND_INTEGRATION_ID');
if ($integrationId === false || trim($integrationId) === '') {
    throw new \RuntimeException('VIEWMEND_INTEGRATION_ID is required.');
}
$integrationId = trim($integrationId);

$viewmend = ViewMend::client(token: $token);

$result = $viewmend
    ->siteTracker($integrationId)
    ->events()
    ->deployment(
        id: 'deploy-abc123',
        title: 'Homepage deployed',
    )
    ->send();
```

The default versioned API base URL is `https://viewmend.com/api/v1`. Creating and enriching an event performs no network request; the side effect occurs only when `send()` is called.

## Add change context

Fluent methods add validated context while keeping the event immutable:

```php
$result = $viewmend
    ->siteTracker($integrationId)
    ->events()
    ->deployment(
        id: 'deploy-abc123',
        title: 'Homepage deployed',
    )
    ->site('https://example.com')
    ->page('https://example.com/')
    ->page('https://example.com/pricing')
    ->environment('production')
    ->description('Published release abc123.')
    ->reference('https://github.com/example/project/actions/runs/123')
    ->send();
```

`site()` identifies the affected site, while each `page()` adds a specific tracked-page URL. Supported semantic event methods are `deployment()`, `contentUpdate()`, `pluginUpdate()`, `themeUpdate()`, `cacheCleared()`, `trackingScriptChange()`, `maintenance()`, and `custom()`.

For optional integration-declared `changed_fields` and `metadata`, see [Site Tracker event context](docs/event-context.md).

Use an event ID that is unique and stable for the originating change. Safe retries send the identical serialized payload and the same event ID. If the server already accepted that ID, it returns a duplicate delivery instead of creating a second event.

## Handle the result

`send()` returns a typed `DeliveryResult`:

```php
printf(
    "Delivery %s: event %s is %s (%s)\n",
    $result->deliveryId->value,
    $result->eventId->value,
    $result->duplicate ? 'a duplicate' : 'accepted',
    $result->queueStatus->value,
);
```

`QueueStatus` preserves unknown future values. Use `isKnown()` for display decisions, but retain its raw `value` rather than treating a new server status as a malformed response.

All SDK failures extend `ViewMend\Exception\ViewMendException`. Significant API statuses have dedicated exception types:

- `AuthenticationException` for 401
- `EndpointDisabledException` for 410
- `PayloadTooLargeException` for 413
- `UnprocessableEventException` for 422
- `RateLimitException` for exhausted 429 responses
- `ServerException` for exhausted 5xx responses
- `NetworkException` for exhausted PSR-18 network failures
- `TransportException` for other non-retryable transport failures
- `UnexpectedResponseException` for unexpected status codes or malformed successful JSON

Exception messages and SDK log context do not include authorization headers, API tokens, or raw response bodies.

## Advanced configuration

The SDK uses Guzzle by default. Applications that manage their own HTTP infrastructure can inject a PSR-18 client and PSR-17 factories. See [Advanced configuration](docs/advanced-configuration.md).

## License

The ViewMend PHP SDK is available under the [MIT License](LICENSE).

## Development

```bash
composer validate --no-check-publish
composer test
composer analyse
composer cs:check
composer quality
```

Tests use mock PSR-18 clients and never send real network traffic or perform real sleeps. Architectural decisions and public boundaries are recorded in [docs/architecture.md](docs/architecture.md).
