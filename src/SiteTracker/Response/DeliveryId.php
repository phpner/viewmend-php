<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\Validation\Utf8;

final readonly class DeliveryId
{
    public function __construct(public string $value)
    {
        Utf8::assertNotBlank($value, 'delivery_id');
        Utf8::assertMax($value, 255, 'delivery_id');
    }
}
