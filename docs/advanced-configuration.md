# Advanced configuration

The default `ViewMend::client()` factory includes and configures Guzzle. Most applications do not need anything from this document.

## Inject a PSR-18 client

Applications that manage their own HTTP infrastructure can inject any PSR-18 client and PSR-17 request and stream factories:

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use ViewMend\ViewMend;

require __DIR__ . '/vendor/autoload.php';

$token = $_ENV['VIEWMEND_API_TOKEN']
    ?? throw new \RuntimeException('VIEWMEND_API_TOKEN is required.');
$httpClient = new Client();
$httpFactory = new HttpFactory();

$viewmend = ViewMend::withPsr18(
    token: $token,
    httpClient: $httpClient,
    requestFactory: $httpFactory,
    streamFactory: $httpFactory,
);
```

The injected transport receives the same authentication, retry, response mapping, and redaction behavior as the default Guzzle transport.

## Override the API base URL

Tests and self-hosted installations can supply a versioned `apiBaseUrl` through the advanced factory:

```php
$viewmend = ViewMend::withPsr18(
    token: $token,
    httpClient: $httpClient,
    requestFactory: $httpFactory,
    streamFactory: $httpFactory,
    apiBaseUrl: 'https://self-hosted.example/api/v1',
);
```

The version prefix belongs in `apiBaseUrl`. A Site Tracker request appends `/site-tracker/integrations/{integration}/events` to that base URL.
