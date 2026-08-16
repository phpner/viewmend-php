<?php

declare(strict_types=1);

namespace ViewMend\Core\Config;

use Closure;
use ViewMend\Core\Exception\ConfigurationException;

final readonly class ApiToken
{
    /** @var Closure(): string */
    private Closure $reader;

    public function __construct(#[\SensitiveParameter] string $value)
    {
        if ($value === '' || strlen($value) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ConfigurationException('The API token is invalid.');
        }

        $this->reader = static fn (): string => $value;
    }

    /** @internal */
    public function reveal(): string
    {
        return ($this->reader)();
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
