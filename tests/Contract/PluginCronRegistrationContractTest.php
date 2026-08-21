<?php

declare(strict_types=1);

namespace ViewMend\Tests\Contract;

use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ViewMend\Exception\UnprocessableRegistrationException;
use ViewMend\Tests\Support\SequenceHttpClient;
use ViewMend\ViewMend;

final class PluginCronRegistrationContractTest extends TestCase
{
    /** @throws JsonException */
    public function testRegistersExactPluginCronContractAndParsesResponse(): void
    {
        $http = new SequenceHttpClient([$this->successResponse(201)]);
        $key = $this->connectionKey();
        $result = $this->sdk($http, $key)->pluginCron()->register(
            cron: '*/15 * * * *',
            timezone: 'Europe/London',
            endpointPath: '/wp-json/viewmend/v1/cron',
            pluginId: 'viewmend-wordpress',
            pluginVersion: '1.2.0',
        );

        self::assertCount(1, $http->requests);
        $request = $http->requests[0];
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(
            'https://viewmend.com/api/v1/plugin-cron/registration',
            (string) $request->getUri(),
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            hash('sha256', 'Bearer ' . $key),
            hash('sha256', $request->getHeaderLine('Authorization')),
        );
        self::assertSame([
            'schedule' => [
                'cron' => '*/15 * * * *',
                'timezone' => 'Europe/London',
            ],
            'endpoint_path' => '/wp-json/viewmend/v1/cron',
            'plugin' => [
                'id' => 'viewmend-wordpress',
                'version' => '1.2.0',
            ],
            'enabled' => true,
        ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));

        self::assertSame('cron_' . str_repeat('c', 26), $result->id);
        self::assertSame('example.com', $result->domain);
        self::assertSame('https://example.com/wp-json/viewmend/v1/cron', $result->endpointUrl);
        self::assertSame('POST', $result->method);
        self::assertSame('pending_verification', $result->status);
        self::assertSame('2026-08-21T09:00:00+00:00', $result->updatedAt?->format(DATE_ATOM));
    }

    public function testReadsMissingRegistrationAsNullAndDisableIsIdempotent(): void
    {
        $http = new SequenceHttpClient([
            new Response(404, ['Content-Type' => 'application/json'], '{"error":{"code":"registration_not_found"}}'),
            new Response(204),
        ]);
        $client = $this->sdk($http, $this->connectionKey())->pluginCron();

        self::assertNull($client->current());
        $client->disable();

        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame('DELETE', $http->requests[1]->getMethod());
        self::assertTrue($http->isExhausted());
    }

    public function testMapsInvalidRegistrationWithoutExposingServerBody(): void
    {
        $secret = 'do-not-expose-this-server-value';
        $http = new SequenceHttpClient([
            new Response(422, [], json_encode(['secret' => $secret], JSON_THROW_ON_ERROR)),
        ]);

        try {
            $this->sdk($http, $this->connectionKey())->pluginCron()->register(
                cron: '*/5 * * * *',
                timezone: 'UTC',
                endpointPath: '/cron',
                pluginId: 'example',
            );
            self::fail('Expected registration to be rejected.');
        } catch (UnprocessableRegistrationException $exception) {
            self::assertSame(422, $exception->statusCode);
            self::assertStringNotContainsString($secret, $exception->getMessage());
        }
    }

    private function sdk(SequenceHttpClient $http, string $key): ViewMend
    {
        $factory = new Psr17Factory();

        return ViewMend::withPsr18(
            token: $key,
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    private function successResponse(int $status): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'data' => [
                'id' => 'cron_' . str_repeat('c', 26),
                'connection_id' => 'pcn_' . str_repeat('a', 26),
                'domain' => 'example.com',
                'endpoint_path' => '/wp-json/viewmend/v1/cron',
                'endpoint_url' => 'https://example.com/wp-json/viewmend/v1/cron',
                'method' => 'POST',
                'plugin' => ['id' => 'viewmend-wordpress', 'version' => '1.2.0'],
                'schedule' => ['cron' => '*/15 * * * *', 'timezone' => 'Europe/London'],
                'enabled' => true,
                'status' => 'pending_verification',
                'verified_at' => null,
                'next_run_at' => null,
                'last_run_at' => null,
                'consecutive_failures' => 0,
                'updated_at' => '2026-08-21T09:00:00+00:00',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function connectionKey(): string
    {
        return 'vmcron1_pcn_' . str_repeat('a', 26) . '_' . str_repeat('A', 64) . '_' . str_repeat('S', 64);
    }
}
