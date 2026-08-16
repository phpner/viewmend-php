<?php

declare(strict_types=1);

namespace ViewMend\Exception;

final class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $statusCode,
        ?string $deliveryId,
        public readonly ?string $retryAfter,
    ) {
        parent::__construct($message, $statusCode, $deliveryId);
    }
}
