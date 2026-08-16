<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ViewMend\SiteTracker\Response\QueueStatus;

final class QueueStatusTest extends TestCase
{
    public function testKnownStatusIsRecognised(): void
    {
        $status = new QueueStatus(QueueStatus::QUEUED);

        self::assertTrue($status->isKnown());
        self::assertTrue($status->is(QueueStatus::QUEUED));
    }

    public function testUnknownFutureStatusIsPreserved(): void
    {
        $status = new QueueStatus('waiting_for_regional_capacity');

        self::assertFalse($status->isKnown());
        self::assertSame('waiting_for_regional_capacity', $status->value);
    }
}
