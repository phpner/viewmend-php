<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker\Dashboard;

use JsonException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\ResourceNotFoundException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\UnprocessableQueryException;
use ViewMend\Exception\ValidationException;
use ViewMend\Internal\Contracts\Http\TransportInterface;
use ViewMend\Internal\Http\ApiErrorMapper;
use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\SiteTracker\IntegrationId;
use ViewMend\Internal\Validation\Utf8;
use ViewMend\SiteTracker\Response\DashboardResult;
use ViewMend\SiteTracker\Response\ResourcesResult;

/** @internal */
final readonly class Reader
{
    public function __construct(
        private TransportInterface $transport,
        private IntegrationId $integration,
        private ResponseMapper $mapper = new ResponseMapper(),
        private ApiErrorMapper $errors = new ApiErrorMapper(),
    ) {
    }

    public function dashboard(?string $pageId, string $device): DashboardResult
    {
        $this->validateDevice($device);
        if (
            $pageId !== null && preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',
                $pageId,
            ) !== 1
        ) {
            throw new ValidationException('Site Tracker pageId must be a UUID.');
        }

        return $this->get('/dashboard', ['page_id' => $pageId, 'device' => $device], $this->mapper->dashboard(...));
    }

    public function resources(string $runId, string $type, string $device, int $page, int $perPage): ResourcesResult
    {
        Utf8::assertNotBlank($runId, 'Site Tracker run ID');
        Utf8::assertMax($runId, 255, 'Site Tracker run ID');
        $this->validateDevice($device);
        if (!in_array($type, ['images', 'javascript', 'css', 'other'], true)) {
            throw new ValidationException('Site Tracker resource type must be images, javascript, css, or other.');
        }
        Values::positive($page);
        Values::pageSize($perPage);

        return $this->get('/runs/' . rawurlencode($runId) . '/resources', [
            'type' => $type,
            'device' => $device,
            'page' => $page,
            'per_page' => $perPage,
        ], $this->mapper->resources(...));
    }

    private function validateDevice(string $device): void
    {
        if (!in_array($device, ['desktop', 'mobile'], true)) {
            throw new ValidationException('Site Tracker device must be desktop or mobile.');
        }
    }

    /**
     * @template T
     * @param array<string, string|int|null> $query
     * @param callable(ResponseObject): T $map
     * @return T
     */
    private function get(string $path, array $query, callable $map): mixed
    {
        $response = $this->transport->send(new HttpRequest(
            method: 'GET',
            path: '/site-tracker/integrations/' . $this->integration->asPathSegment() . $path
                . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            headers: ['Accept' => 'application/json'],
            retrySafe: true,
        ));

        if ($response->statusCode !== 200) {
            throw match ($response->statusCode) {
                404 => new ResourceNotFoundException(
                    'The Site Tracker resource was not found in this integration.',
                    404,
                ),
                410 => new EndpointDisabledException('The ViewMend Site Tracker dashboard endpoint is disabled.', 410),
                422 => new UnprocessableQueryException('ViewMend rejected the Site Tracker query parameters.', 422),
                default => $this->errors->fromResponse($response),
            };
        }

        try {
            return $map(new ResponseObject(json_decode($response->body, false, 512, JSON_THROW_ON_ERROR)));
        } catch (JsonException | ValidationException) {
            throw new UnexpectedResponseException('ViewMend returned a malformed Site Tracker read response.', 200);
        }
    }
}
