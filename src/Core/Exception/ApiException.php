<?php

declare(strict_types=1);

namespace ViewMend\Core\Exception;

class ApiException extends ViewMendException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $deliveryId = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
