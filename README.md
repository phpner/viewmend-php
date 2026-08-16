# ViewMend PHP SDK

ViewMend Site Tracker monitors changes to selected website pages. Deployment, content, cache, and maintenance events can be linked to the checks that follow them, making it easier to understand what changed and why.

This SDK lets a PHP application send that change context to ViewMend. The event then appears in the shared Events and Timeline workflow and can trigger checks for affected tracked pages.

[Learn more about ViewMend Site Tracker](https://viewmend.com/site-tracker)

> A public license has not yet been selected. Packagist distribution and release tags will begin after licensing is finalized.

## Installation

```bash
composer require viewmend/sdk
```

That command installs everything required to send events. Guzzle is included as the SDK's default HTTP transport; application code does not need to install, configure, or import it.

## Quick Start

```php
<?php

declare(strict_types=1);

use ViewMend\ViewMend;

require __DIR__ . '/vendor/autoload.php';

$token = $_ENV['VIEWMEND_API_TOKEN']
    ?? throw new \RuntimeException('VIEWMEND_API_TOKEN is required.');
$integrationId = $_ENV['VIEWMEND_INTEGRATION_ID']
    ?? throw new \RuntimeException('VIEWMEND_INTEGRATION_ID is required.');

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
    ->contentChanged()
    ->metadataChanged()
    ->metadata(['commit' => 'abc123'])
    ->send();
```

`site()` identifies the affected site, while each `page()` adds a specific tracked-page URL. Supported semantic event methods are `deployment()`, `contentUpdate()`, `pluginUpdate()`, `themeUpdate()`, `cacheCleared()`, `trackingScriptChange()`, `maintenance()`, and `custom()`.

`contentChanged()` and `metadataChanged()` add the corresponding values to `changed_fields` without relying on error-prone string literals. For a field that does not have a semantic SDK method, use the explicit escape hatch `customFieldChanged('product_schema')`.

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

## Development

```bash
composer validate --no-check-publish
composer test
composer analyse
composer cs:check
composer quality
```

Tests use mock PSR-18 clients and never send real network traffic or perform real sleeps. Architectural decisions and public boundaries are recorded in [docs/architecture.md](docs/architecture.md).
