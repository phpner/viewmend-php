<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ViewMend\Internal\Config\ClientConfig;
use ViewMend\Exception\ConfigurationException;

final class ClientConfigTest extends TestCase
{
    public function testConfigNormalizesVersionedApiBaseUrl(): void
    {
        $config = new ClientConfig('token', 'https://viewmend.com/api/v1/');

        self::assertSame('https://viewmend.com/api/v1', $config->apiBaseUrl->value);
    }

    public function testConfigDebugOutputRedactsToken(): void
    {
        $token = implode('', ['vmt', '_', bin2hex(random_bytes(8))]);
        $config = new ClientConfig($token, 'https://viewmend.com/api/v1');

        $output = print_r($config, true);
        $export = var_export($config, true);

        self::assertStringNotContainsString($token, $output);
        self::assertStringNotContainsString($token, $export);
        self::assertStringContainsString('[REDACTED]', $output);
    }

    public function testBaseUrlRejectsEmbeddedCredentials(): void
    {
        $this->expectException(ConfigurationException::class);
        new ClientConfig('token', 'https://user:pass@viewmend.com/api/v1');
    }

    public function testTokenRejectsHeaderInjectionWithoutEchoingSecret(): void
    {
        $token = "secret\r\nX-Leak: yes";

        try {
            new ClientConfig($token, 'https://viewmend.com/api/v1');
            self::fail('Expected invalid token to fail.');
        } catch (ConfigurationException $exception) {
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }
}
