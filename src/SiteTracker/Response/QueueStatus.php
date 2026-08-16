<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use ViewMend\Internal\Validation\Utf8;

final readonly class QueueStatus
{
    public const RECORDED = 'recorded';
    public const QUEUED = 'queued';
    public const WAITING_FOR_CREDITS = 'waiting_for_credits';
    public const WAITING_FOR_PAGES = 'waiting_for_pages';
    public const PAUSED = 'paused';
    public const PROCESSED = 'processed';
    public const CANCELLED = 'cancelled';
    public const IGNORED_NO_MATCHING_PAGES = 'ignored_no_matching_pages';
    public const IGNORED_NO_ACTIVE_PAGES = 'ignored_no_active_pages';

    /** @var list<string> */
    private const KNOWN = [
        self::RECORDED,
        self::QUEUED,
        self::WAITING_FOR_CREDITS,
        self::WAITING_FOR_PAGES,
        self::PAUSED,
        self::PROCESSED,
        self::CANCELLED,
        self::IGNORED_NO_MATCHING_PAGES,
        self::IGNORED_NO_ACTIVE_PAGES,
    ];

    public function __construct(public string $value)
    {
        Utf8::assertNotBlank($value, 'queue_status');
        Utf8::assertMax($value, 120, 'queue_status');
    }

    public function isKnown(): bool
    {
        return in_array($this->value, self::KNOWN, true);
    }

    public function is(string $status): bool
    {
        return $this->value === $status;
    }
}
