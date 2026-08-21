<?php

declare(strict_types=1);

namespace ViewMend\Tests\Contract;

use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ViewMend\Exception\TokenScopeException;
use ViewMend\Exception\UnprocessableRegistrationException;
use ViewMend\Tests\Support\SequenceHttpClient;
use ViewMend\ViewMend;

final class CronRegistrationContractTest extends TestCase
{
    /** @throws JsonException */
    public function testRegistersExactCronContractAndParsesResponse(): void
    {
        $http = new SequenceHttpClient([$this->successResponse(201)]);
        $token = $this->token();
        $result = $this->sdk($http, $token)->cron()->register(
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
            'https://viewmend.com/api/v1/cron/registration',
            (string) $request->getUri(),
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            hash('sha256', 'Bearer ' . $token),
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
        $client = $this->sdk($http, $this->token())->cron();

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
            $this->sdk($http, $this->token())->cron()->register(
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

    public function testMapsSiteTrackerTokenToDedicatedScopeError(): void
    {
        $serverMessage = 'do-not-trust-or-expose-this-server-message';
        $http = new SequenceHttpClient([
            new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'error' => [
                    'code' => 'token_scope_invalid',
                    'message' => $serverMessage,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        try {
            $this->sdk($http, 'vmt_' . str_repeat('x', 32))->cron()->current();
            self::fail('Expected the Site Tracker token to be rejected by Cron.');
        } catch (TokenScopeException $exception) {
            self::assertSame(401, $exception->statusCode);
            self::assertSame(
                'This token is for the Site Tracker API and cannot be used with the Cron API. '
                    . 'Use the connection token issued in Integrations.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($serverMessage, $exception->getMessage());
        }
    }

    private function sdk(SequenceHttpClient $http, string $token): ViewMend
    {
        $factory = new Psr17Factory();

        return ViewMend::withPsr18(
            token: $token,
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
                'connection_id' => 'cronconn_' . str_repeat('a', 26),
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

    private function token(): string
    {
        return 'vmcron1_cronconn_' . str_repeat('a', 26) . '_' . str_repeat('A', 64) . '_' . str_repeat('S', 64);
    }
}
