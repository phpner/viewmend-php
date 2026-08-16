<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker;

use ViewMend\Internal\Validation\Utf8;

final readonly class IntegrationId
{
    public string $value;

    public function __construct(string $value)
    {
        Utf8::assertNotBlank($value, 'Site Tracker integration ID');
        Utf8::assertMax($value, 255, 'Site Tracker integration ID');

        $this->value = $value;
    }

    public function asPathSegment(): string
    {
        return rawurlencode($this->value);
    }
}
