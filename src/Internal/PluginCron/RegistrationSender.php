<?php

declare(strict_types=1);

namespace ViewMend\Internal\PluginCron;

use DateTimeImmutable;
use Exception;
use JsonException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\ValidationException;
use ViewMend\Internal\Contracts\Http\TransportInterface;
use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\Http\HttpResponse;
use ViewMend\Cron\Response\RegistrationResult;

/** @internal */
final readonly class RegistrationSender
{
    private const PATH = '/cron/registration';

    public function __construct(
        private TransportInterface $transport,
        private PluginCronApiErrorMapper $errors = new PluginCronApiErrorMapper(),
    ) {
    }

    public function upsert(
        string $cron,
        string $timezone,
        string $endpointPath,
        string $pluginId,
        ?string $pluginVersion,
        bool $enabled,
    ): RegistrationResult {
        $payload = $this->registrationPayload(
            $cron,
            $timezone,
            $endpointPath,
            $pluginId,
            $pluginVersion,
            $enabled,
        );

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new ValidationException('The Cron registration could not be encoded as JSON.');
        }

        $response = $this->transport->send(new HttpRequest(
            method: 'PUT',
            path: self::PATH,
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            body: $body,
            retrySafe: true,
        ));

        if (! in_array($response->statusCode, [200, 201], true)) {
            throw $this->errors->fromResponse($response);
        }

        return $this->parseSuccess($response);
    }

    public function current(): ?RegistrationResult
    {
        $response = $this->transport->send(new HttpRequest(
            method: 'GET',
            path: self::PATH,
            headers: ['Accept' => 'application/json'],
            retrySafe: true,
        ));

        if ($response->statusCode === 404) {
            return null;
        }

        if ($response->statusCode !== 200) {
            throw $this->errors->fromResponse($response);
        }

        return $this->parseSuccess($response);
    }

    public function disable(): void
    {
        $response = $this->transport->send(new HttpRequest(
            method: 'DELETE',
            path: self::PATH,
            headers: ['Accept' => 'application/json'],
            retrySafe: true,
        ));

        if ($response->statusCode !== 204) {
            throw $this->errors->fromResponse($response);
        }
    }

    /** @return array<string, mixed> */
    private function registrationPayload(
        string $cron,
        string $timezone,
        string $endpointPath,
        string $pluginId,
        ?string $pluginVersion,
        bool $enabled,
    ): array {
        $cron = trim($cron);
        $timezone = trim($timezone);
        $endpointPath = trim($endpointPath);
        $pluginId = trim($pluginId);
        $pluginVersion = $pluginVersion === null ? null : trim($pluginVersion);

        if ($cron === '' || strlen($cron) > 100) {
            throw new ValidationException('The Cron expression is invalid.');
        }

        if ($timezone === '' || strlen($timezone) > 64) {
            throw new ValidationException('The Cron timezone is invalid.');
        }

        if (
            $endpointPath === ''
            || strlen($endpointPath) > 512
            || ! str_starts_with($endpointPath, '/')
            || str_starts_with($endpointPath, '//')
            || str_contains($endpointPath, '\\')
            || str_contains($endpointPath, '?')
            || str_contains($endpointPath, '#')
            || preg_match('#(^|/)\.\.(/|$)#', $endpointPath) === 1
        ) {
            throw new ValidationException('The Cron endpoint must be an absolute path without a host or query.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/D', $pluginId) !== 1) {
            throw new ValidationException('The Cron plugin ID is invalid.');
        }

        if ($pluginVersion === '') {
            $pluginVersion = null;
        }

        if ($pluginVersion !== null && strlen($pluginVersion) > 80) {
            throw new ValidationException('The Cron plugin version is invalid.');
        }

        return [
            'schedule' => [
                'cron' => $cron,
                'timezone' => $timezone,
            ],
            'endpoint_path' => $endpointPath,
            'plugin' => [
                'id' => $pluginId,
                'version' => $pluginVersion,
            ],
            'enabled' => $enabled,
        ];
    }

    private function parseSuccess(HttpResponse $response): RegistrationResult
    {
        try {
            $document = json_decode($response->body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->malformed($response);
        }

        if (! is_array($document) || array_is_list($document)) {
            throw $this->malformed($response);
        }

        $data = $document['data'] ?? null;
        if (! is_array($data) || array_is_list($data)) {
            throw $this->malformed($response);
        }

        $plugin = $data['plugin'] ?? null;
        $schedule = $data['schedule'] ?? null;
        if (
            ! is_array($plugin) || array_is_list($plugin)
            || ! is_array($schedule) || array_is_list($schedule)
        ) {
            throw $this->malformed($response);
        }

        $enabled = $data['enabled'] ?? null;
        $failures = $data['consecutive_failures'] ?? null;
        if (! is_bool($enabled) || ! is_int($failures) || $failures < 0) {
            throw $this->malformed($response);
        }

        $method = $this->string($data, 'method', $response);
        $endpointUrl = $this->string($data, 'endpoint_url', $response);
        $endpoint = parse_url($endpointUrl);
        if (
            $method !== 'POST'
            || filter_var($endpointUrl, FILTER_VALIDATE_URL) === false
            || ! is_array($endpoint)
            || strtolower((string) ($endpoint['scheme'] ?? '')) !== 'https'
            || ! is_string($endpoint['host'] ?? null)
            || isset($endpoint['user'])
            || isset($endpoint['pass'])
        ) {
            throw $this->malformed($response);
        }

        return new RegistrationResult(
            id: $this->string($data, 'id', $response),
            connectionId: $this->string($data, 'connection_id', $response),
            domain: $this->string($data, 'domain', $response),
            endpointPath: $this->string($data, 'endpoint_path', $response),
            endpointUrl: $endpointUrl,
            method: $method,
            pluginId: $this->string($plugin, 'id', $response),
            pluginVersion: $this->nullableString($plugin, 'version', $response),
            cron: $this->string($schedule, 'cron', $response),
            timezone: $this->string($schedule, 'timezone', $response),
            enabled: $enabled,
            status: $this->string($data, 'status', $response),
            verifiedAt: $this->nullableDate($data, 'verified_at', $response),
            nextRunAt: $this->nullableDate($data, 'next_run_at', $response),
            lastRunAt: $this->nullableDate($data, 'last_run_at', $response),
            consecutiveFailures: $failures,
            updatedAt: $this->nullableDate($data, 'updated_at', $response),
        );
    }

    /** @param array<mixed> $data */
    private function string(array $data, string $key, HttpResponse $response): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw $this->malformed($response);
        }

        return $value;
    }

    /** @param array<mixed> $data */
    private function nullableString(array $data, string $key, HttpResponse $response): ?string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return $this->string($data, $key, $response);
    }

    /** @param array<mixed> $data */
    private function nullableDate(array $data, string $key, HttpResponse $response): ?DateTimeImmutable
    {
        $value = $this->nullableString($data, $key, $response);
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw $this->malformed($response);
        }
    }

    private function malformed(HttpResponse $response): UnexpectedResponseException
    {
        return new UnexpectedResponseException(
            'ViewMend returned a malformed Cron response.',
            $response->statusCode,
            $response->headerLine('X-Request-Id'),
        );
    }
}
