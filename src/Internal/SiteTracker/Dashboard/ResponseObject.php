<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker\Dashboard;

use DateTimeImmutable;
use stdClass;
use ViewMend\Exception\ValidationException;

/** @internal */
final readonly class ResponseObject
{
    private stdClass $data;

    public function __construct(mixed $data)
    {
        if (!$data instanceof stdClass) {
            throw new ValidationException('Expected a Site Tracker JSON object.');
        }
        $this->data = $data;
    }

    public function value(string $key): mixed
    {
        if (!property_exists($this->data, $key)) {
            throw new ValidationException('A required Site Tracker response field is missing.');
        }

        return $this->data->{$key};
    }

    public function object(string $key): self
    {
        return new self($this->value($key));
    }

    public function string(string $key): string
    {
        $value = $this->value($key);
        if (!is_string($value)) {
            throw new ValidationException('Expected a Site Tracker string.');
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        return $this->value($key) === null ? null : $this->string($key);
    }

    public function integer(string $key): int
    {
        $value = $this->value($key);
        if (!is_int($value)) {
            throw new ValidationException('Expected a Site Tracker integer.');
        }

        return $value;
    }

    public function nullableInteger(string $key): ?int
    {
        return $this->value($key) === null ? null : $this->integer($key);
    }

    public function nullableNumber(string $key): ?float
    {
        $value = $this->value($key);
        if ($value === null) {
            return null;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new ValidationException('Expected a finite Site Tracker number.');
        }

        return (float) $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->value($key);
        if (!is_bool($value)) {
            throw new ValidationException('Expected a Site Tracker boolean.');
        }

        return $value;
    }

    public function date(string $key): DateTimeImmutable
    {
        $value = $this->string($key);
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D',
                $value,
            ) !== 1
        ) {
            throw new ValidationException('Expected a Site Tracker RFC 3339 timestamp.');
        }

        $format = str_contains($value, '.') ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ValidationException('Expected a valid Site Tracker calendar timestamp.');
        }

        return $date;
    }

    public function nullableDate(string $key): ?DateTimeImmutable
    {
        return $this->value($key) === null ? null : $this->date($key);
    }

    /**
     * @template T
     * @param callable(self): T $map
     * @return list<T>
     */
    public function mapList(string $key, callable $map): array
    {
        $items = $this->value($key);
        if (!is_array($items) || !array_is_list($items)) {
            throw new ValidationException('Expected a Site Tracker JSON list.');
        }

        return array_map(static fn (mixed $item) => $map(new self($item)), $items);
    }
}
