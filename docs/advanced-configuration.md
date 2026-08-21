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

$token = getenv('VIEWMEND_API_TOKEN');
if ($token === false || trim($token) === '') {
    throw new \RuntimeException('VIEWMEND_API_TOKEN is required.');
}
$token = trim($token);

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

## Default timeouts

The default Guzzle transport allows 3 seconds to establish a connection and 10 seconds for each complete request attempt. TLS certificate verification remains enabled and automatic redirects are disabled. Safe network failures can be attempted at most three times, so a timeout-only failure is bounded to approximately 31 seconds including exponential backoff.

## Override the API base URL

Self-hosted installations can supply a versioned `apiBaseUrl` while retaining the default transport:

```php
$viewmend = ViewMend::client(
    token: $token,
    apiBaseUrl: 'https://self-hosted.example/api/v1',
);
```

Tests and applications with a managed HTTP stack can pass the same value through the advanced factory:

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

Cron callback URLs must use HTTPS. HTTP is accepted only for loopback hosts and `host.docker.internal`, so local development remains possible without weakening production callback validation.
