<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker\Dashboard;

use ViewMend\Exception\ValidationException;
use ViewMend\Internal\Validation\Utf8;

/** @internal */
final class Values
{
    public static function text(?string $value): void
    {
        if ($value !== null) {
            Utf8::length($value, 'Site Tracker value');
        }
    }

    public static function identifier(?string $value): void
    {
        if ($value !== null) {
            Utf8::assertNotBlank($value, 'Site Tracker identifier or status');
        }
    }

    public static function counter(?int $value): void
    {
        if ($value !== null && $value < 0) {
            throw new ValidationException('Site Tracker counters must not be negative.');
        }
    }

    public static function positive(int $value): void
    {
        if ($value < 1) {
            throw new ValidationException('Site Tracker pagination values must be positive.');
        }
    }

    public static function pageSize(int $value): void
    {
        self::positive($value);
        if ($value > 300) {
            throw new ValidationException('Site Tracker perPage must not exceed 300.');
        }
    }

    public static function finite(?float $value): void
    {
        if ($value !== null && !is_finite($value)) {
            throw new ValidationException('Site Tracker measurements must be finite.');
        }
    }

    /**
     * @template T of object
     * @param array<mixed> $values
     * @param class-string<T> $class
     * @return list<T>
     */
    public static function listOf(array $values, string $class): array
    {
        if (!array_is_list($values)) {
            throw new ValidationException('Site Tracker collections must be lists.');
        }

        $result = [];
        foreach ($values as $value) {
            if (!$value instanceof $class) {
                throw new ValidationException('A Site Tracker collection contains an invalid item.');
            }
            $result[] = $value;
        }

        return $result;
    }
}
