<?php

declare(strict_types=1);

namespace ViewMend\Internal\Config;

use ViewMend\Exception\ConfigurationException;

final readonly class ApiBaseUrl
{
    public string $value;

    public function __construct(string $value)
    {
        $value = rtrim($value, '/');
        $parts = parse_url($value);

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new ConfigurationException(
                'The API base URL must be an absolute HTTP(S) URL without credentials, query, or fragment.',
            );
        }

        $this->value = $value;
    }

    public function resolve(string $path): string
    {
        if (!str_starts_with($path, '/')) {
            throw new ConfigurationException('API request paths must start with a slash.');
        }

        return $this->value . $path;
    }
}
