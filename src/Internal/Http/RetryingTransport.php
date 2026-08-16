<?php

declare(strict_types=1);

namespace ViewMend\Internal\Http;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ViewMend\Internal\Contracts\Http\TransportInterface;
use ViewMend\Exception\TransportException;
use ViewMend\Internal\Retry\RetryPolicyInterface;
use ViewMend\Internal\Retry\SleeperInterface;

final readonly class RetryingTransport implements TransportInterface
{
    public function __construct(
        private TransportInterface $transport,
        private RetryPolicyInterface $policy,
        private SleeperInterface $sleeper,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $attemptsMade = 0;

        while (true) {
            $attemptsMade++;

            try {
                $response = $this->transport->send($request);
                $delay = $this->policy->delayBeforeRetry($attemptsMade, $request, $response, null);
                if ($delay === null) {
                    return $response;
                }

                $this->logRetry($attemptsMade, $delay, $response->statusCode);
            } catch (TransportException $exception) {
                $delay = $this->policy->delayBeforeRetry($attemptsMade, $request, null, $exception);
                if ($delay === null) {
                    throw $exception;
                }

                $this->logRetry($attemptsMade, $delay, null);
            }

            $this->sleeper->sleep($delay);
        }
    }

    private function logRetry(int $attemptsMade, float $delay, ?int $statusCode): void
    {
        $this->logger->notice('Retrying a safe ViewMend HTTP request.', [
            'attempts_made' => $attemptsMade,
            'delay_seconds' => $delay,
            'status_code' => $statusCode,
        ]);
    }
}
