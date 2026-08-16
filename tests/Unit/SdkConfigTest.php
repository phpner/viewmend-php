<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ViewMend\Core\Config\SdkConfig;
use ViewMend\Core\Exception\ConfigurationException;

final class SdkConfigTest extends TestCase
{
    public function testConfigNormalizesBaseUrlAndEncodesIntegrationPath(): void
    {
        $config = new SdkConfig('https://app.viewmend.com/', 'token', 'project/id');

        self::assertSame('https://app.viewmend.com', $config->apiBaseUrl->value);
        self::assertSame('project%2Fid', $config->integration->asPathSegment());
    }

    public function testConfigDebugOutputRedactsToken(): void
    {
        $token = implode('', ['vmt', '_', bin2hex(random_bytes(8))]);
        $config = new SdkConfig('https://app.viewmend.com', $token, 'integration-1');

        $output = print_r($config, true);
        $export = var_export($config, true);

        self::assertStringNotContainsString($token, $output);
        self::assertStringNotContainsString($token, $export);
        self::assertStringContainsString('[REDACTED]', $output);
    }

    public function testBaseUrlRejectsEmbeddedCredentials(): void
    {
        $this->expectException(ConfigurationException::class);
        new SdkConfig('https://user:pass@app.viewmend.com', 'token', 'integration-1');
    }

    public function testTokenRejectsHeaderInjectionWithoutEchoingSecret(): void
    {
        $token = "secret\r\nX-Leak: yes";

        try {
            new SdkConfig('https://app.viewmend.com', $token, 'integration-1');
            self::fail('Expected invalid token to fail.');
        } catch (ConfigurationException $exception) {
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }
}
