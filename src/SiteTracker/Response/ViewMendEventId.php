<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Core\Validation\Utf8;

final readonly class ViewMendEventId
{
    public function __construct(public string $value)
    {
        Utf8::assertNotBlank($value, 'ViewMend event_id');
        Utf8::assertMax($value, 255, 'ViewMend event_id');
    }
}
