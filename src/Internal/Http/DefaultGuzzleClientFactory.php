<?php

declare(strict_types=1);

namespace ViewMend\Internal\Http;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

final class DefaultGuzzleClientFactory
{
    public const CONNECT_TIMEOUT_SECONDS = 3.0;

    public const TOTAL_TIMEOUT_SECONDS = 10.0;

    public static function create(): Client
    {
        return new Client([
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            RequestOptions::TIMEOUT => self::TOTAL_TIMEOUT_SECONDS,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);
    }
}
