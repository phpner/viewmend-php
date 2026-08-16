<?php

declare(strict_types=1);

namespace ViewMend\Internal\Config;

final readonly class ClientConfig
{
    public ApiBaseUrl $apiBaseUrl;

    public ApiToken $apiToken;

    public function __construct(
        #[\SensitiveParameter] string $apiToken,
        string $apiBaseUrl,
    ) {
        $this->apiBaseUrl = new ApiBaseUrl($apiBaseUrl);
        $this->apiToken = new ApiToken($apiToken);
    }

    /** @return array{apiBaseUrl: string, apiToken: string} */
    public function __debugInfo(): array
    {
        return [
            'apiBaseUrl' => $this->apiBaseUrl->value,
            'apiToken' => '[REDACTED]',
        ];
    }
}
