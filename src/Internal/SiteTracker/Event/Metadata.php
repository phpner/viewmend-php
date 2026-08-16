<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker\Event;

use JsonException;
use ViewMend\Exception\ValidationException;

final readonly class Metadata
{
    private string $json;

    /** @param array<mixed>|object $value */
    public function __construct(array|object $value)
    {
        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationException('metadata must be JSON-serializable.');
        }

        if (!is_array($decoded) && !is_object($decoded)) {
            throw new ValidationException('metadata must serialize as a JSON object or array.');
        }

        $this->json = $json;
    }

    /** @return array<array-key, mixed>|object */
    public function value(): array|object
    {
        try {
            $decoded = json_decode($this->json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationException('metadata could not be decoded.');
        }

        if (!is_array($decoded) && !is_object($decoded)) {
            throw new ValidationException('metadata is invalid.');
        }

        return $decoded;
    }
}
