<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use JsonSerializable;
use PHPUnit\Framework\TestCase;
use ViewMend\Exception\ValidationException;
use ViewMend\Internal\SiteTracker\Event\EventId;
use ViewMend\Internal\SiteTracker\Event\EventType;
use ViewMend\Internal\SiteTracker\Event\HttpUrl;
use ViewMend\Internal\SiteTracker\Event\Metadata;
use ViewMend\Internal\SiteTracker\Event\SiteTrackerEvent;

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
