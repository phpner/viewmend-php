<?php

declare(strict_types=1);

namespace ViewMend\Internal\Http;

use ViewMend\Exception\ValidationException;

final readonly class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public string $body = '',
        public bool $retrySafe = false,
    ) {
        if ($method === '' || strtoupper($method) !== $method) {
            throw new ValidationException('The HTTP method must be uppercase.');
        }

        if (!str_starts_with($path, '/')) {
            throw new ValidationException('The HTTP path must start with a slash.');
        }

        foreach ($headers as $name => $value) {
            if ($name === '' || preg_match('/[\r\n]/', $name . $value) === 1) {
                throw new ValidationException('HTTP headers must not contain control line breaks.');
            }
        }
    }
}
