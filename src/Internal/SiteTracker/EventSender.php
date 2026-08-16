<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker;

use DateTimeImmutable;
use Exception;
use JsonException;
use ViewMend\Internal\Contracts\Http\TransportInterface;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\ValidationException;
use ViewMend\Internal\Http\ApiErrorMapper;
use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\Http\HttpResponse;
use ViewMend\Internal\SiteTracker\Event\SiteTrackerEvent;
use ViewMend\SiteTracker\Response\DeliveryId;
use ViewMend\SiteTracker\Response\DeliveryResult;
use ViewMend\SiteTracker\Response\QueueStatus;
use ViewMend\SiteTracker\Response\ViewMendEventId;

final readonly class EventSender
{
    public function __construct(
        private TransportInterface $transport,
        private IntegrationId $integration,
        private ApiErrorMapper $errors = new ApiErrorMapper(),
    ) {
    }

    public function send(SiteTrackerEvent $event): DeliveryResult
    {
        $body = $this->encodeEvent($event);
        $response = $this->transport->send(new HttpRequest(
            method: 'POST',
            path: sprintf(
                '/site-tracker/integrations/%s/events',
                $this->integration->asPathSegment(),
            ),
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            body: $body,
            retrySafe: true,
        ));

        if (!in_array($response->statusCode, [200, 202], true)) {
            throw $this->errors->fromResponse($response);
        }

        return $this->parseSuccess($response);
    }

    private function encodeEvent(SiteTrackerEvent $event): string
    {
        $payload = [
            'event_id' => $event->eventId->value,
            'event_type' => $event->eventType->value,
            'title' => $event->title,
        ];

        $optional = [
            'occurred_at' => $event->occurredAt?->format(DATE_ATOM),
            'site_url' => $event->siteUrl?->value,
            'page_urls' => $event->pageUrls === []
                ? null
                : array_map(static fn ($url): string => $url->value, $event->pageUrls),
            'environment' => $event->environment,
            'description' => $event->description,
            'reference_url' => $event->referenceUrl?->value,
            'changed_fields' => $event->changedFields === [] ? null : $event->changedFields,
            'metadata' => $event->metadata?->value(),
        ];

        foreach ($optional as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new ValidationException('The Site Tracker event could not be encoded as JSON.');
        }
    }

    private function parseSuccess(HttpResponse $response): DeliveryResult
    {
        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->malformed($response);
        }

        if (!is_array($data) || array_is_list($data) || ($data['ok'] ?? null) !== true) {
            throw $this->malformed($response);
        }

        $deliveryId = $this->requiredString($data, 'delivery_id', $response);
        $headerDeliveryId = $response->headerLine('X-ViewMend-Delivery');
        if ($headerDeliveryId === null || $headerDeliveryId !== $deliveryId) {
            throw $this->malformed($response);
        }

        $duplicate = $data['duplicate'] ?? null;
        if (!is_bool($duplicate) || ($response->statusCode === 200) !== $duplicate) {
            throw $this->malformed($response);
        }

        $affectedPages = $this->requiredCounter($data, 'affected_pages', $response);
        $ignoredUrls = $this->requiredCounter($data, 'ignored_urls', $response);
        $checksQueued = $this->requiredCounter($data, 'checks_queued', $response);
        $queueStatus = $this->requiredString($data, 'queue_status', $response);
        $scheduledFor = $this->scheduledFor($data, $response);

        try {
            return new DeliveryResult(
                deliveryId: new DeliveryId($deliveryId),
                eventId: new ViewMendEventId($this->requiredString($data, 'event_id', $response)),
                duplicate: $duplicate,
                affectedPages: $affectedPages,
                ignoredUrls: $ignoredUrls,
                checksQueued: $checksQueued,
                queueStatus: new QueueStatus($queueStatus),
                scheduledFor: $scheduledFor,
            );
        } catch (ValidationException) {
            throw $this->malformed($response);
        }
    }

    /** @param array<mixed> $data */
    private function requiredString(array $data, string $key, HttpResponse $response): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw $this->malformed($response);
        }

        return $value;
    }

    /** @param array<mixed> $data */
    private function requiredCounter(array $data, string $key, HttpResponse $response): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw $this->malformed($response);
        }

        return $value;
    }

    /** @param array<mixed> $data */
    private function scheduledFor(array $data, HttpResponse $response): ?DateTimeImmutable
    {
        if (!array_key_exists('scheduled_for', $data)) {
            throw $this->malformed($response);
        }

        $value = $data['scheduled_for'];
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw $this->malformed($response);
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
            'ViewMend returned a malformed Site Tracker response.',
            $response->statusCode,
            $response->headerLine('X-ViewMend-Delivery'),
        );
    }
}
