<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Event;

use DateTimeImmutable;

final class SiteTrackerEventBuilder
{
    private ?DateTimeImmutable $occurredAt = null;

    private ?HttpUrl $siteUrl = null;

    /** @var list<HttpUrl> */
    private array $pageUrls = [];

    private ?string $environment = null;

    private ?string $description = null;

    private ?HttpUrl $referenceUrl = null;

    /** @var list<string> */
    private array $changedFields = [];

    private ?Metadata $metadata = null;

    public function __construct(
        private readonly EventId $eventId,
        private readonly EventType $eventType,
        private readonly string $title,
    ) {
    }

    public function occurredAt(DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function siteUrl(string $siteUrl): self
    {
        $this->siteUrl = new HttpUrl($siteUrl);

        return $this;
    }

    public function pageUrls(string ...$pageUrls): self
    {
        $this->pageUrls = array_values(array_map(
            static fn (string $url): HttpUrl => new HttpUrl($url),
            $pageUrls,
        ));

        return $this;
    }

    public function environment(string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function referenceUrl(string $referenceUrl): self
    {
        $this->referenceUrl = new HttpUrl($referenceUrl);

        return $this;
    }

    public function changedFields(string ...$changedFields): self
    {
        $this->changedFields = array_values($changedFields);

        return $this;
    }

    /** @param array<mixed>|object $metadata */
    public function metadata(array|object $metadata): self
    {
        $this->metadata = new Metadata($metadata);

        return $this;
    }

    public function build(): SiteTrackerEvent
    {
        return new SiteTrackerEvent(
            eventId: $this->eventId,
            eventType: $this->eventType,
            title: $this->title,
            occurredAt: $this->occurredAt,
            siteUrl: $this->siteUrl,
            pageUrls: $this->pageUrls,
            environment: $this->environment,
            description: $this->description,
            referenceUrl: $this->referenceUrl,
            changedFields: $this->changedFields,
            metadata: $this->metadata,
        );
    }
}
