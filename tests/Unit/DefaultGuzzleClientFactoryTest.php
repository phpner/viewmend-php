<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use ViewMend\Internal\Http\DefaultGuzzleClientFactory;

final class DefaultGuzzleClientFactoryTest extends TestCase
{
    public function testCreatesAClientWithBoundedSafeDefaultsWithoutSendingARequest(): void
    {
        $client = DefaultGuzzleClientFactory::create();

        self::assertInstanceOf(ClientInterface::class, $client);
        self::assertSame(
            DefaultGuzzleClientFactory::CONNECT_TIMEOUT_SECONDS,
            $client->getConfig(RequestOptions::CONNECT_TIMEOUT),
        );
        self::assertSame(
            DefaultGuzzleClientFactory::TOTAL_TIMEOUT_SECONDS,
            $client->getConfig(RequestOptions::TIMEOUT),
        );
        self::assertTrue($client->getConfig(RequestOptions::VERIFY));
        self::assertFalse($client->getConfig(RequestOptions::ALLOW_REDIRECTS));
    }
}
