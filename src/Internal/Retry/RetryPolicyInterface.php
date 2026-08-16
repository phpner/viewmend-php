<?php

declare(strict_types=1);

namespace ViewMend\Internal\Retry;

use ViewMend\Exception\TransportException;
use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\Http\HttpResponse;

interface RetryPolicyInterface
{
    public function delayBeforeRetry(
        int $attemptsMade,
        HttpRequest $request,
        ?HttpResponse $response,
        ?TransportException $exception,
    ): ?float;
}
