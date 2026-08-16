<?php

declare(strict_types=1);

namespace ViewMend;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ViewMend\Core\Config\SdkConfig;
use ViewMend\Core\Http\Psr18Transport;
use ViewMend\Core\Http\RetryingTransport;
use ViewMend\Core\Retry\ClockInterface;
use ViewMend\Core\Retry\ExponentialBackoffRetryPolicy;
use ViewMend\Core\Retry\NativeSleeper;
use ViewMend\Core\Retry\RetryPolicyInterface;
use ViewMend\Core\Retry\SleeperInterface;
use ViewMend\Core\Retry\SystemClock;
use ViewMend\SiteTracker\EventsClient;
use ViewMend\SiteTracker\SiteTrackerClient;

final readonly class ViewMendClient
{
    public function __construct(private SiteTrackerClient $siteTracker)
    {
    }

    public static function create(
        SdkConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        ?RetryPolicyInterface $retryPolicy = null,
        ?ClockInterface $clock = null,
        ?SleeperInterface $sleeper = null,
    ): self {
        $logger ??= new NullLogger();
        $clock ??= new SystemClock();
        $retryPolicy ??= new ExponentialBackoffRetryPolicy($clock);
        $sleeper ??= new NativeSleeper();

        $transport = new RetryingTransport(
            new Psr18Transport($config, $httpClient, $requestFactory, $streamFactory, $logger),
            $retryPolicy,
            $sleeper,
            $logger,
        );
        $events = new EventsClient($transport, $config->integration);

        return new self(new SiteTrackerClient($events));
    }

    public function siteTracker(): SiteTrackerClient
    {
        return $this->siteTracker;
    }
}
