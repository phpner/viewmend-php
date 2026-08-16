<?php

declare(strict_types=1);

namespace ViewMend\Internal\Validation;

use ViewMend\Exception\ValidationException;

/** @internal */
final class Utf8
{
    public static function length(string $value, string $field): int
    {
        if (preg_match('//u', $value) !== 1) {
            throw new ValidationException(sprintf('%s must contain valid UTF-8.', $field));
        }

        $length = preg_match_all('/./us', $value);
        if ($length === false) {
            throw new ValidationException(sprintf('%s could not be validated.', $field));
        }

        return $length;
    }

    public static function assertNotBlank(string $value, string $field): void
    {
        self::length($value, $field);

        if (trim($value) === '') {
            throw new ValidationException(sprintf('%s must not be blank.', $field));
        }
    }

    public static function assertMax(string $value, int $max, string $field): void
    {
        if (self::length($value, $field) > $max) {
            throw new ValidationException(sprintf('%s must not exceed %d characters.', $field, $max));
        }
    }
}
