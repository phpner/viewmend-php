<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker\Event;

use ViewMend\Exception\ValidationException;
use ViewMend\Internal\Validation\Utf8;

final readonly class HttpUrl
{
    public function __construct(public string $value)
    {
        Utf8::assertNotBlank($value, 'URL');
        Utf8::assertMax($value, 2048, 'URL');

        $parts = parse_url($value);
        if (
            filter_var($value, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            throw new ValidationException('URL must be an absolute HTTP(S) URL.');
        }
    }
}
