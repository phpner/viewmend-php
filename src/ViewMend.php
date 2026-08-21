<?php

declare(strict_types=1);

namespace ViewMend;

use GuzzleHttp\Psr7\HttpFactory;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ViewMend\Internal\Config\ClientConfig;
use ViewMend\Internal\Config\ApiToken;
use ViewMend\Internal\Contracts\Http\TransportInterface;
use ViewMend\Internal\Http\DefaultGuzzleClientFactory;
use ViewMend\Internal\Http\Psr18Transport;
use ViewMend\Internal\Http\RetryingTransport;
use ViewMend\Internal\PluginCron\RegistrationSender;
use ViewMend\Internal\Retry\ExponentialBackoffRetryPolicy;
use ViewMend\Internal\Retry\NativeSleeper;
use ViewMend\Internal\Retry\SystemClock;
use ViewMend\Internal\SiteTracker\EventSender;
use ViewMend\Internal\SiteTracker\IntegrationId;
use ViewMend\SiteTracker\SiteTrackerClient;
use ViewMend\Cron\CallbackVerifier;
use ViewMend\Cron\CronClient;

final readonly class ViewMend
{
    public const PRODUCTION_API_BASE_URL = 'https://viewmend.com/api/v1';

    private function __construct(
        private TransportInterface $transport,
        private ?ApiToken $token = null,
    ) {
    }

    public static function client(#[\SensitiveParameter] string $token): self
    {
        $factory = new HttpFactory();

        return self::withPsr18(
            token: $token,
            httpClient: DefaultGuzzleClientFactory::create(),
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    public static function withPsr18(
        #[\SensitiveParameter] string $token,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $apiBaseUrl = self::PRODUCTION_API_BASE_URL,
        ?LoggerInterface $logger = null,
    ): self {
        $logger ??= new NullLogger();
        $config = new ClientConfig($token, $apiBaseUrl);
        $transport = new RetryingTransport(
            new Psr18Transport($config, $httpClient, $requestFactory, $streamFactory, $logger),
            new ExponentialBackoffRetryPolicy(new SystemClock()),
            new NativeSleeper(),
            $logger,
        );

        return new self($transport, $config->apiToken);
    }

    /** @internal */
    public static function fromTransport(TransportInterface $transport): self
    {
        return new self($transport);
    }

    public function siteTracker(string $integrationId): SiteTrackerClient
    {
        return new SiteTrackerClient(new EventSender(
            $this->transport,
            new IntegrationId($integrationId),
        ));
    }

    public function cron(): CronClient
    {
        if ($this->token === null) {
            throw new LogicException('Cron requires a token-backed ViewMend client.');
        }

        return new CronClient(
            new RegistrationSender($this->transport),
            CallbackVerifier::fromToken($this->token->reveal()),
        );
    }
}
