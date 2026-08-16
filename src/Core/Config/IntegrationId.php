<?php

declare(strict_types=1);

namespace ViewMend\Core\Config;

use ViewMend\Core\Exception\ConfigurationException;
use ViewMend\Core\Exception\ValidationException;
use ViewMend\Core\Validation\Utf8;

final readonly class IntegrationId
{
    public string $value;

    public function __construct(string $value)
    {
        try {
            Utf8::assertNotBlank($value, 'Integration ID');
            Utf8::assertMax($value, 255, 'Integration ID');
        } catch (ValidationException $exception) {
            throw new ConfigurationException($exception->getMessage());
        }

        $this->value = $value;
    }

    public function asPathSegment(): string
    {
        return rawurlencode($this->value);
    }
}
