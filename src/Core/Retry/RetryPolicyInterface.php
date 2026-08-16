<?php

declare(strict_types=1);

namespace ViewMend\Core\Retry;

use ViewMend\Core\Exception\TransportException;
use ViewMend\Core\Http\HttpRequest;
use ViewMend\Core\Http\HttpResponse;

interface RetryPolicyInterface
{
    public function delayBeforeRetry(
        int $attemptsMade,
        HttpRequest $request,
        ?HttpResponse $response,
        ?TransportException $exception,
    ): ?float;
}
