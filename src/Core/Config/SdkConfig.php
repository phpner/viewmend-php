<?php

declare(strict_types=1);

namespace ViewMend\Core\Config;

final readonly class SdkConfig
{
    public ApiBaseUrl $apiBaseUrl;

    public ApiToken $apiToken;

    public IntegrationId $integration;

    public function __construct(
        string $apiBaseUrl,
        #[\SensitiveParameter] string $apiToken,
        string $integration,
    ) {
        $this->apiBaseUrl = new ApiBaseUrl($apiBaseUrl);
        $this->apiToken = new ApiToken($apiToken);
        $this->integration = new IntegrationId($integration);
    }

    /** @return array{apiBaseUrl: string, apiToken: string, integration: string} */
    public function __debugInfo(): array
    {
        return [
            'apiBaseUrl' => $this->apiBaseUrl->value,
            'apiToken' => '[REDACTED]',
            'integration' => $this->integration->value,
        ];
    }
}
