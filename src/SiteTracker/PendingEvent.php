<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Event\HttpUrl;
use ViewMend\Internal\SiteTracker\Event\Metadata;
use ViewMend\Internal\SiteTracker\Event\SiteTrackerEvent;
use ViewMend\Internal\SiteTracker\EventSender;
use ViewMend\SiteTracker\Response\DeliveryResult;

final readonly class PendingEvent
{
    /** @internal */
    public function __construct(
        private EventSender $sender,
        private SiteTrackerEvent $event,
    ) {
    }

    public function occurredAt(DateTimeImmutable $occurredAt): self
    {
        return new self($this->sender, $this->event->withOccurredAt($occurredAt));
    }

    public function site(string $url): self
    {
        return new self($this->sender, $this->event->withSiteUrl(new HttpUrl($url)));
    }

    public function page(string $url): self
    {
        return new self($this->sender, $this->event->withPageUrl(new HttpUrl($url)));
    }

    public function environment(string $environment): self
    {
        return new self($this->sender, $this->event->withEnvironment($environment));
    }

    public function description(string $description): self
    {
        return new self($this->sender, $this->event->withDescription($description));
    }

    public function reference(string $url): self
    {
        return new self($this->sender, $this->event->withReferenceUrl(new HttpUrl($url)));
    }

    public function contentChanged(): self
    {
        return $this->fieldChanged('content');
    }

    public function metadataChanged(): self
    {
        return $this->fieldChanged('metadata');
    }

    public function customFieldChanged(string $field): self
    {
        return $this->fieldChanged($field);
    }

    /** @param array<mixed>|object $metadata */
    public function metadata(array|object $metadata): self
    {
        return new self($this->sender, $this->event->withMetadata(new Metadata($metadata)));
    }

    public function send(): DeliveryResult
    {
        return $this->sender->send($this->event);
    }

    private function fieldChanged(string $field): self
    {
        return new self(
            $this->sender,
            $this->event->withChangedFields([...$this->event->changedFields, $field]),
        );
    }
}
