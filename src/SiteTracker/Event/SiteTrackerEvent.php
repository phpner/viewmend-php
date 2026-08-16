<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Event;

use DateTimeImmutable;
use ViewMend\Core\Exception\ValidationException;
use ViewMend\Core\Validation\Utf8;

final readonly class SiteTrackerEvent
{
    /** @var list<HttpUrl> */
    public array $pageUrls;

    /** @var list<string> */
    public array $changedFields;

    /**
     * @param list<mixed> $pageUrls
     * @param list<mixed> $changedFields
     */
    public function __construct(
        public EventId $eventId,
        public EventType $eventType,
        public string $title,
        public ?DateTimeImmutable $occurredAt = null,
        public ?HttpUrl $siteUrl = null,
        array $pageUrls = [],
        public ?string $environment = null,
        public ?string $description = null,
        public ?HttpUrl $referenceUrl = null,
        array $changedFields = [],
        public ?Metadata $metadata = null,
    ) {
        Utf8::assertNotBlank($title, 'title');
        Utf8::assertMax($title, 140, 'title');

        if (count($pageUrls) > 20) {
            throw new ValidationException('page_urls must not contain more than 20 URLs.');
        }

        $validatedPageUrls = [];
        foreach ($pageUrls as $url) {
            if (!$url instanceof HttpUrl) {
                throw new ValidationException('Every page_urls item must be an HttpUrl.');
            }

            $validatedPageUrls[] = $url;
        }
        $this->pageUrls = $validatedPageUrls;

        if ($environment !== null) {
            Utf8::assertMax($environment, 60, 'environment');
        }

        if ($description !== null) {
            Utf8::assertMax($description, 2000, 'description');
        }

        if (count($changedFields) > 50) {
            throw new ValidationException('changed_fields must not contain more than 50 items.');
        }

        $validatedChangedFields = [];
        foreach ($changedFields as $field) {
            if (!is_string($field)) {
                throw new ValidationException('Every changed_fields item must be a string.');
            }

            Utf8::assertMax($field, 120, 'changed_fields item');
            $validatedChangedFields[] = $field;
        }
        $this->changedFields = $validatedChangedFields;
    }

    public static function create(string $eventId, EventType $eventType, string $title): self
    {
        return new self(new EventId($eventId), $eventType, $title);
    }

    public static function builder(string $eventId, EventType $eventType, string $title): SiteTrackerEventBuilder
    {
        return new SiteTrackerEventBuilder(new EventId($eventId), $eventType, $title);
    }
}
