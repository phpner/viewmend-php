<?php

declare(strict_types=1);

namespace ViewMend\Core\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use ViewMend\Contracts\Http\TransportInterface;
use ViewMend\Core\Config\SdkConfig;
use ViewMend\Core\Exception\NetworkException;
use ViewMend\Core\Exception\TransportException;

final readonly class Psr18Transport implements TransportInterface
{
    public function __construct(
        private SdkConfig $config,
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $psrRequest = $this->requestFactory
            ->createRequest($request->method, $this->config->apiBaseUrl->resolve($request->path))
            ->withBody($this->streamFactory->createStream($request->body));

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        $psrRequest = $psrRequest->withHeader(
            'Authorization',
            'Bearer ' . $this->config->apiToken->reveal(),
        );

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (NetworkExceptionInterface $exception) {
            $this->logger->warning('ViewMend HTTP network transport failed.', [
                'exception_type' => $exception::class,
            ]);

            throw new NetworkException('The ViewMend HTTP request could not reach the server.');
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('ViewMend HTTP transport failed.', [
                'exception_type' => $exception::class,
            ]);

            throw new TransportException('The ViewMend HTTP request could not be completed.');
        }

        try {
            $body = (string) $psrResponse->getBody();
        } catch (Throwable $exception) {
            $this->logger->warning('ViewMend HTTP response body could not be read.', [
                'exception_type' => $exception::class,
            ]);

            throw new TransportException('The ViewMend HTTP response could not be read.');
        }

        return new HttpResponse(
            statusCode: $psrResponse->getStatusCode(),
            body: $body,
            headers: $this->redactedHeaders($psrResponse->getHeaders()),
        );
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     * @return array<string, list<string>>
     */
    private function redactedHeaders(array $headers): array
    {
        $token = $this->config->apiToken->reveal();
        $redacted = [];

        foreach ($headers as $name => $values) {
            $redacted[(string) $name] = array_values(array_map(
                static fn (string $value): string => str_replace($token, '[REDACTED]', $value),
                $values,
            ));
        }

        return $redacted;
    }
}
