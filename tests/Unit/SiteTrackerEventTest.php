<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use DateTimeImmutable;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use stdClass;
use ViewMend\Core\Exception\ValidationException;
use ViewMend\SiteTracker\Event\EventId;
use ViewMend\SiteTracker\Event\EventType;
use ViewMend\SiteTracker\Event\HttpUrl;
use ViewMend\SiteTracker\Event\Metadata;
use ViewMend\SiteTracker\Event\SiteTrackerEvent;

final class SiteTrackerEventTest extends TestCase
{
    public function testEventTypesMatchTheApiV1InputContract(): void
    {
        self::assertSame([
            'deployment',
            'content_update',
            'plugin_update',
            'theme_update',
            'cache_cleared',
            'tracking_script_change',
            'maintenance',
            'custom',
        ], array_column(EventType::cases(), 'value'));
    }

    public function testBuilderCreatesACompleteImmutableEvent(): void
    {
        $event = SiteTrackerEvent::builder('deploy-42', EventType::Deployment, 'Homepage deployed')
            ->occurredAt(new DateTimeImmutable('2026-08-16T12:00:00+00:00'))
            ->siteUrl('https://example.com')
            ->pageUrls('https://example.com/', 'https://example.com/pricing')
            ->environment('production')
            ->description('Published the release.')
            ->referenceUrl('https://example.com/releases/42')
            ->changedFields('content', 'metadata')
            ->metadata(['commit' => 'abc123'])
            ->build();

        self::assertSame('deploy-42', $event->eventId->value);
        self::assertSame(EventType::Deployment, $event->eventType);
        self::assertSame('https://example.com/pricing', $event->pageUrls[1]->value);
        self::assertSame(['content', 'metadata'], $event->changedFields);
        self::assertInstanceOf(stdClass::class, $event->metadata?->value());
    }

    public function testEventIdUsesUnicodeCharacterLength(): void
    {
        new EventId(str_repeat('é', 160));
        self::addToAssertionCount(1);

        $this->expectException(ValidationException::class);
        new EventId(str_repeat('é', 161));
    }

    public function testTitleCannotExceedApiLimit(): void
    {
        $this->expectException(ValidationException::class);
        SiteTrackerEvent::create('event-1', EventType::Custom, str_repeat('x', 141));
    }

    public function testPageUrlsCannotExceedApiLimit(): void
    {
        $this->expectException(ValidationException::class);
        new SiteTrackerEvent(
            new EventId('event-1'),
            EventType::Custom,
            'Many pages',
            pageUrls: array_fill(0, 21, new HttpUrl('https://example.com')),
        );
    }

    public function testChangedFieldsCannotExceedApiLimit(): void
    {
        $this->expectException(ValidationException::class);
        new SiteTrackerEvent(
            new EventId('event-1'),
            EventType::Custom,
            'Many fields',
            changedFields: array_fill(0, 51, 'content'),
        );
    }

    public function testChangedFieldCannotExceedServerItemLimit(): void
    {
        $this->expectException(ValidationException::class);
        new SiteTrackerEvent(
            new EventId('event-1'),
            EventType::Custom,
            'Long field',
            changedFields: [str_repeat('x', 121)],
        );
    }

    public function testOnlyAbsoluteHttpUrlsAreAccepted(): void
    {
        $this->expectException(ValidationException::class);
        new HttpUrl('file:///private/tmp/example');
    }

    public function testMetadataMustBeJsonSerializable(): void
    {
        $this->expectException(ValidationException::class);
        new Metadata(['not-json' => INF]);
    }

    public function testMetadataObjectMustRemainAnObjectOrArray(): void
    {
        $invalid = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'not-an-object';
            }
        };

        $this->expectException(ValidationException::class);
        new Metadata($invalid);
    }
}
