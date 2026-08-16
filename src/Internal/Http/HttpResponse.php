<?php

declare(strict_types=1);

namespace ViewMend\Internal\Http;

use ViewMend\Exception\TransportException;

final readonly class HttpResponse
{
    /** @var array<string, list<string>> */
    private array $normalizedHeaders;

    /**
     * @param array<string, list<string>|string> $headers
     */
    public function __construct(
        public int $statusCode,
        public string $body,
        array $headers = [],
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new TransportException('The HTTP response status is invalid.');
        }

        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = is_array($values) ? $values : [$values];
        }

        $this->normalizedHeaders = $normalized;
    }

    public function headerLine(string $name): ?string
    {
        $values = $this->normalizedHeaders[strtolower($name)] ?? null;

        return $values === null ? null : implode(', ', $values);
    }
}
